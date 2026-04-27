-- Adicionar colunas faltantes na tabela partners
-- Execute este script no Query Editor do Azure Portal

-- Verificar se as colunas já existem antes de adicionar
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('partners') AND name = 'contact_phone')
BEGIN
    ALTER TABLE partners ADD contact_phone NVARCHAR(50);
    PRINT 'Coluna contact_phone adicionada';
END
ELSE
BEGIN
    PRINT 'Coluna contact_phone já existe';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('partners') AND name = 'contact_role')
BEGIN
    ALTER TABLE partners ADD contact_role NVARCHAR(100);
    PRINT 'Coluna contact_role adicionada';
END
ELSE
BEGIN
    PRINT 'Coluna contact_role já existe';
END

PRINT 'Migração concluída com sucesso!';
