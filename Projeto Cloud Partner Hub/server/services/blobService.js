// ============================================================================
// services/blobService.js — Armazenamento de arquivos de proposta no Azure Blob
// ----------------------------------------------------------------------------
// Decisões (ponto 4):
//   - Arquivo pesado (XLSX/PDF + JSON bruto) vai para o Blob; só KPIs no SQL.
//   - Download via SAS de curta duração (link assinado, não público).
//   - Tudo configurável por env; se a connection string não existir, o serviço
//     fica "desabilitado" e os endpoints retornam 503 (não quebra o resto).
//   - Retenção/lifecycle (12m->Archive, 24m->expurgo) é configurado na própria
//     Storage Account (regra de lifecycle), não em código.
// ============================================================================

import {
  BlobServiceClient,
  StorageSharedKeyCredential,
  generateBlobSASQueryParameters,
  BlobSASPermissions,
  SASProtocol,
} from '@azure/storage-blob';
import { config } from '../config/governance.js';

let serviceClient = null;
let sharedKeyCredential = null;
let accountName = null;

function parseAccountFromConnString(conn) {
  const name = /AccountName=([^;]+)/i.exec(conn)?.[1];
  const key = /AccountKey=([^;]+)/i.exec(conn)?.[1];
  return { name, key };
}

function init() {
  if (serviceClient !== null) return;
  const conn = config.blob.connectionString;
  if (!conn) return; // permanece desabilitado

  serviceClient = BlobServiceClient.fromConnectionString(conn);
  const { name, key } = parseAccountFromConnString(conn);
  accountName = name || null;
  // Credencial de chave compartilhada é necessária para gerar SAS.
  if (name && key) {
    sharedKeyCredential = new StorageSharedKeyCredential(name, key);
  }
}

export function isBlobEnabled() {
  init();
  return serviceClient !== null;
}

async function getContainer() {
  init();
  if (!serviceClient) throw Object.assign(new Error('Blob Storage não configurado.'), {
    httpStatus: 503,
  });
  const container = serviceClient.getContainerClient(config.blob.containerName);
  await container.createIfNotExists(); // sem acesso público
  return container;
}

/**
 * Faz upload de um buffer e retorna o caminho (nome do blob) salvo.
 * @param {string} blobName  caminho lógico, ex.: "<companyId>/<proposalId>/arquivo.xlsx"
 * @param {Buffer} buffer
 * @param {string} contentType
 */
export async function uploadBuffer(blobName, buffer, contentType = 'application/octet-stream') {
  const container = await getContainer();
  const blockBlob = container.getBlockBlobClient(blobName);
  await blockBlob.uploadData(buffer, {
    blobHTTPHeaders: { blobContentType: contentType },
  });
  return blobName;
}

/**
 * Gera uma URL de download assinada (SAS) de curta duração para um blob.
 * @param {string} blobName
 * @returns {Promise<string>} URL completa com SAS
 */
export async function getDownloadUrl(blobName) {
  const container = await getContainer();
  const blob = container.getBlockBlobClient(blobName);

  if (!sharedKeyCredential) {
    // Sem chave compartilhada não há como assinar; retorna erro claro.
    throw Object.assign(
      new Error('SAS indisponível: connection string sem AccountKey.'),
      { httpStatus: 503 }
    );
  }

  const now = new Date();
  const expiresOn = new Date(now.getTime() + config.blob.sasTtlMinutes * 60 * 1000);

  const sas = generateBlobSASQueryParameters(
    {
      containerName: config.blob.containerName,
      blobName,
      permissions: BlobSASPermissions.parse('r'), // somente leitura
      startsOn: new Date(now.getTime() - 60 * 1000), // tolerância de clock
      expiresOn,
      protocol: SASProtocol.Https,
    },
    sharedKeyCredential
  ).toString();

  return `${blob.url}?${sas}`;
}
