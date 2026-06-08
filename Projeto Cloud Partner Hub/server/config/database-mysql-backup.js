import mysql from 'mysql2/promise';
import dotenv from 'dotenv';

dotenv.config();

// Configuração SSL para Azure Database for MySQL
const sslConfig = process.env.DB_SSL === 'true' ? {
  ssl: {
    rejectUnauthorized: true
  }
} : {};

const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'cloudpartner_hub',
  port: process.env.DB_PORT || 3306,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  ...sslConfig
});

// Testar conexão
pool.getConnection()
  .then(connection => {
    console.log('✅ MySQL conectado com sucesso!');
    connection.release();
  })
  .catch(err => {
    console.error('❌ Erro ao conectar MySQL:', err.message);
  });

export default pool;
