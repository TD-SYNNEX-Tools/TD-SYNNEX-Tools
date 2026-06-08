// ============================================================================
// migrate-governance.js — Aplica o schema do Portal de Governança T2
// ----------------------------------------------------------------------------
// Lê schema-governance.sql, divide nos batches "GO" (mssql não interpreta GO)
// e executa cada batch em sequência.
//
// Uso:
//   node database/migrate-governance.js
//
// Requer .env com as variáveis de conexão (DB_HOST, DB_NAME, DB_USER,
// DB_PASSWORD, DB_PORT) — as mesmas usadas por config/database.js.
// Para desenvolvimento local, aponte para um SQL Server local / container.
// ============================================================================

import sql from 'mssql';
import dotenv from 'dotenv';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const config = {
  server: process.env.DB_HOST || 'localhost',
  database: process.env.DB_NAME || 'cloudpartner_hub',
  port: parseInt(process.env.DB_PORT || '1433', 10),
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  options: {
    encrypt: process.env.DB_ENCRYPT !== 'false',
    trustServerCertificate: process.env.DB_TRUST_CERT === 'true',
    enableArithAbort: true,
    connectTimeout: 30000,
    requestTimeout: 60000,
  },
  pool: { max: 5, min: 0, idleTimeoutMillis: 30000 },
};

// Divide o script em batches separados por linhas contendo apenas "GO".
function splitBatches(sqlText) {
  return sqlText
    .split(/^\s*GO\s*$/gim)
    .map((b) => b.trim())
    .filter((b) => b.length > 0);
}

async function migrate() {
  const schemaPath = path.join(__dirname, 'schema-governance.sql');
  const sqlText = fs.readFileSync(schemaPath, 'utf8');
  const batches = splitBatches(sqlText);

  console.log('🔄 Migração de governança T2 — iniciando...');
  console.log(`📄 ${batches.length} batches a executar de schema-governance.sql`);

  let pool;
  try {
    pool = await sql.connect(config);
    console.log(`✅ Conectado a ${config.server}/${config.database}`);

    for (let i = 0; i < batches.length; i++) {
      const batch = batches[i];
      try {
        await pool.request().batch(batch);
        console.log(`  ✓ Batch ${i + 1}/${batches.length} aplicado`);
      } catch (err) {
        console.error(`  ✗ Batch ${i + 1}/${batches.length} falhou:`, err.message);
        throw err;
      }
    }

    console.log('🎉 Schema de governança T2 aplicado com sucesso.');
  } catch (err) {
    console.error('❌ Erro na migração:', err.message);
    process.exitCode = 1;
  } finally {
    if (pool) await pool.close();
    console.log('🔚 Migração finalizada.');
  }
}

migrate();
