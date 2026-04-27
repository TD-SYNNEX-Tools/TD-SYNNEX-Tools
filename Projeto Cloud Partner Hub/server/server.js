import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'url';
import partnersRoutes from './routes/partners.js';
import pool from './config/database.js';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || process.env.WEBSITES_PORT || 8080;

// Middlewares
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Servir arquivos estáticos do frontend (produção)
app.use(express.static(path.join(__dirname, 'public')));

// Health check
app.get('/api/health', (req, res) => {
  res.json({ status: 'ok', message: 'CloudPartner HUB API rodando' });
});

// Rota de migração (apenas para desenvolvimento)
app.get('/api/migrate', async (req, res) => {
  try {
    // Adicionar colunas contact_phone e contact_role se não existirem
    const migrations = [
      `ALTER TABLE partners ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(50)`,
      `ALTER TABLE partners ADD COLUMN IF NOT EXISTS contact_role VARCHAR(100)`
    ];
    
    for (const sql of migrations) {
      try {
        await pool.query(sql);
      } catch (e) {
        // Ignorar erros de coluna já existente
        if (!e.message.includes('Duplicate column')) {
          console.log('Migration warning:', e.message);
        }
      }
    }
    
    res.json({ success: true, message: 'Migração executada! Colunas contact_phone e contact_role adicionadas.' });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

// Routes
app.use('/api/partners', partnersRoutes);

// SPA Fallback - serve index.html para rotas do frontend (produção)
app.get('*', (req, res, next) => {
  if (req.path.startsWith('/api')) {
    return next();
  }
  res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// Error handling
app.use((err, req, res, next) => {
  console.error(err.stack);
  res.status(500).json({ error: 'Erro interno do servidor' });
});

// Start server
app.listen(PORT, () => {
  console.log(`🚀 Servidor rodando na porta ${PORT}`);
  console.log(`📍 API: http://localhost:${PORT}/api`);
  console.log(`🌍 Ambiente: ${process.env.NODE_ENV || 'development'}`);
});

export default app;
