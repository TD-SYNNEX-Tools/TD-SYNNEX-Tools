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

console.log('🔌 Testando conexão com Azure SQL Database...');
console.log('Server:', config.server);
console.log('Database:', config.database);
console.log('Auth:', config.authentication.type);

try {
  const pool = await sql.connect(config);
  console.log('✅ Conexão estabelecida com sucesso!');
  
  const result = await pool.request().query('SELECT @@VERSION as version');
  console.log('📊 Versão do SQL Server:', result.recordset[0].version);
  
  const tables = await pool.request().query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
  console.log('📋 Tabelas encontradas:', tables.recordset.length);
  tables.recordset.forEach(t => console.log('  -', t.TABLE_NAME));
  
  await pool.close();
  console.log('✅ Teste concluído com sucesso!');
} catch (err) {
  console.error('❌ Erro ao conectar:', err.message);
  console.error('Código:', err.code);
  process.exit(1);
}
