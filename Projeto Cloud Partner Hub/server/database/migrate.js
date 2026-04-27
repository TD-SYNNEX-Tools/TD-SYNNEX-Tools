import mysql from 'mysql2/promise';
import dotenv from 'dotenv';

dotenv.config();

async function migrate() {
  const connection = await mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'cloudpartner_hub',
    port: process.env.DB_PORT || 3306
  });

  try {
    console.log('🔄 Iniciando migração do banco de dados...');
    
    // Alterar pcs_skilling de DECIMAL para INT
    await connection.query(`
      ALTER TABLE partners 
      MODIFY COLUMN pcs_skilling INT DEFAULT 0
    `);
    
    console.log('✅ Coluna pcs_skilling atualizada com sucesso!');
    
    // Verificar a estrutura atualizada
    const [columns] = await connection.query(`
      SHOW COLUMNS FROM partners WHERE Field = 'pcs_skilling'
    `);
    
    console.log('📊 Estrutura atual da coluna:', columns);
    
  } catch (error) {
    console.error('❌ Erro na migração:', error.message);
  } finally {
    await connection.end();
    console.log('🔚 Migração concluída.');
  }
}

migrate();
