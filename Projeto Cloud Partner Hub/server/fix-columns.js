import sql from 'mssql';
import dotenv from 'dotenv';

dotenv.config();

const config = {
  server: 'cloudpartnerhubserver.database.windows.net',
  database: 'cloudpartnerhub-db',
  port: 1433,
  authentication: {
    type: 'azure-active-directory-default'
  },
  options: {
    encrypt: true,
    trustServerCertificate: false,
    connectTimeout: 30000
  }
};

console.log('🔧 Adicionando colunas faltantes...');

try {
  const pool = await sql.connect(config);
  
  // Verificar e adicionar contact_phone
  const checkPhone = await pool.request().query(`
    SELECT * FROM sys.columns 
    WHERE object_id = OBJECT_ID('partners') AND name = 'contact_phone'
  `);
  
  if (checkPhone.recordset.length === 0) {
    await pool.request().query('ALTER TABLE partners ADD contact_phone NVARCHAR(50)');
    console.log('✅ Coluna contact_phone adicionada');
  } else {
    console.log('ℹ️  Coluna contact_phone já existe');
  }
  
  // Verificar e adicionar contact_role
  const checkRole = await pool.request().query(`
    SELECT * FROM sys.columns 
    WHERE object_id = OBJECT_ID('partners') AND name = 'contact_role'
  `);
  
  if (checkRole.recordset.length === 0) {
    await pool.request().query('ALTER TABLE partners ADD contact_role NVARCHAR(100)');
    console.log('✅ Coluna contact_role adicionada');
  } else {
    console.log('ℹ️  Coluna contact_role já existe');
  }
  
  // Verificar estrutura final
  const columns = await pool.request().query(`
    SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'partners'
    ORDER BY ORDINAL_POSITION
  `);
  
  console.log('\n📋 Colunas da tabela partners:');
  columns.recordset.forEach(col => {
    console.log(`  - ${col.COLUMN_NAME} (${col.DATA_TYPE}${col.CHARACTER_MAXIMUM_LENGTH ? '(' + col.CHARACTER_MAXIMUM_LENGTH + ')' : ''})`);
  });
  
  await pool.close();
  console.log('\n✅ Migração concluída com sucesso!');
} catch (err) {
  console.error('❌ Erro:', err.message);
  process.exit(1);
}
