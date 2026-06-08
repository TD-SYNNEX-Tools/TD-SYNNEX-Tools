import sql from 'mssql';
import dotenv from 'dotenv';

dotenv.config();

// Configuração do Azure SQL Database
const useAzureAD = process.env.USE_AZURE_AD_AUTH === 'true';

const config = {
  server: process.env.DB_HOST || 'localhost',
  database: process.env.DB_NAME || 'cloudpartner_hub',
  port: parseInt(process.env.DB_PORT || '1433'),
  options: {
    encrypt: true,
    trustServerCertificate: false,
    enableArithAbort: true,
    connectTimeout: 30000,
    requestTimeout: 30000
  },
  pool: {
    max: 10,
    min: 0,
    idleTimeoutMillis: 30000
  }
};

// Adicionar autenticação baseada no tipo
if (useAzureAD) {
  // Azure Active Directory Default Authentication
  config.authentication = {
    type: 'azure-active-directory-default'
  };
  console.log('🔐 Usando Azure Active Directory Authentication');
} else {
  // SQL Authentication tradicional
  config.user = process.env.DB_USER;
  config.password = process.env.DB_PASSWORD;
  console.log('🔐 Usando SQL Authentication');
}

let pool = null;

async function getPool() {
  if (!pool) {
    try {
      pool = await sql.connect(config);
      console.log('✅ Azure SQL Database conectado com sucesso!');
      console.log(`📊 Database: ${config.database}`);
      console.log(`🖥️  Server: ${config.server}`);
    } catch (err) {
      console.error('❌ Erro ao conectar Azure SQL Database:', err.message);
      console.error('Detalhes:', err);
      throw err;
    }
  }
  return pool;
}

// Inicializar conexão
getPool().catch(err => {
  console.error('❌ Falha na inicialização do pool:', err.message);
});

// Adapter para compatibilidade com sintaxe MySQL
const dbAdapter = {
  async query(queryString, params = []) {
    try {
      const poolConnection = await getPool();
      const request = poolConnection.request();
      
      let adaptedQuery = queryString;
      if (params && params.length > 0) {
        params.forEach((param, index) => {
          // Tratar null e undefined
          if (param === null || param === undefined) {
            request.input(`param${index}`, sql.NVarChar, null);
            // Substituir a primeira ocorrência de '?' encontrada
            const firstQuestionMark = adaptedQuery.indexOf('?');
            if (firstQuestionMark !== -1) {
              adaptedQuery = adaptedQuery.substring(0, firstQuestionMark) + 
                             `@param${index}` + 
                             adaptedQuery.substring(firstQuestionMark + 1);
            }
            return;
          }
          
          let sqlType = sql.NVarChar;
          let value = param;
          
          // Detectar tipo e converter
          if (typeof param === 'number') {
            sqlType = Number.isInteger(param) ? sql.Int : sql.Decimal(15, 2);
          } else if (typeof param === 'boolean') {
            sqlType = sql.Bit;
          } else if (typeof param === 'string') {
            // Manter como string
            sqlType = sql.NVarChar;
          } else if (typeof param === 'object') {
            // Converter objeto para string JSON
            sqlType = sql.NVarChar;
            value = JSON.stringify(param);
          }
          
          request.input(`param${index}`, sqlType, value);
          // Substituir a primeira ocorrência de '?' encontrada
          const firstQuestionMark = adaptedQuery.indexOf('?');
          if (firstQuestionMark !== -1) {
            adaptedQuery = adaptedQuery.substring(0, firstQuestionMark) + 
                           `@param${index}` + 
                           adaptedQuery.substring(firstQuestionMark + 1);
          }
        });
      }
      
      console.log('📝 Executando query:', adaptedQuery.substring(0, 150) + '...');
      console.log('📊 Parâmetros:', params.map((p, i) => `@param${i}=${p}`).join(', '));
      const result = await request.query(adaptedQuery);
      console.log('✅ Query executada com sucesso. Registros afetados:', result.rowsAffected);
      
      return [result.recordset || [], result];
    } catch (error) {
      console.error('❌ Erro na query:', error.message);
      console.error('Query original:', queryString);
      console.error('Query adaptada:', adaptedQuery);
      console.error('Parâmetros:', params);
      throw error;
    }
  },
  
  async getConnection() {
    const poolConnection = await getPool();
    const transaction = new sql.Transaction(poolConnection);
    
    return {
      _transaction: null,
      
      async query(queryString, params = []) {
        const request = this._transaction 
          ? new sql.Request(this._transaction)
          : poolConnection.request();
        
        let adaptedQuery = queryString;
        if (params && params.length > 0) {
          params.forEach((param, index) => {
            // Tratar null e undefined
            if (param === null || param === undefined) {
              request.input(`param${index}`, sql.NVarChar, null);
              // Substituir a primeira ocorrência de '?' encontrada
              const firstQuestionMark = adaptedQuery.indexOf('?');
              if (firstQuestionMark !== -1) {
                adaptedQuery = adaptedQuery.substring(0, firstQuestionMark) + 
                               `@param${index}` + 
                               adaptedQuery.substring(firstQuestionMark + 1);
              }
              return;
            }
            
            let sqlType = sql.NVarChar;
            let value = param;
            
            // Detectar tipo e converter
            if (typeof param === 'number') {
              sqlType = Number.isInteger(param) ? sql.Int : sql.Decimal(15, 2);
            } else if (typeof param === 'boolean') {
              sqlType = sql.Bit;
            } else if (typeof param === 'string') {
              sqlType = sql.NVarChar;
            } else if (typeof param === 'object') {
              sqlType = sql.NVarChar;
              value = JSON.stringify(param);
            }
            
            request.input(`param${index}`, sqlType, value);
            // Substituir a primeira ocorrência de '?' encontrada
            const firstQuestionMark = adaptedQuery.indexOf('?');
            if (firstQuestionMark !== -1) {
              adaptedQuery = adaptedQuery.substring(0, firstQuestionMark) + 
                             `@param${index}` + 
                             adaptedQuery.substring(firstQuestionMark + 1);
            }
          });
        }
        
        console.log('📝 [TRANSAÇÃO] Query:', adaptedQuery.substring(0, 150) + '...');
        console.log('📊 [TRANSAÇÃO] Parâmetros:', params.map((p, i) => `@param${i}=${p}`).join(', '));
        const result = await request.query(adaptedQuery);
        return [result.recordset || [], result];
      },
      
      async beginTransaction() {
        this._transaction = transaction;
        await transaction.begin();
        console.log('🔄 Transação iniciada');
      },
      
      async commit() {
        if (this._transaction) {
          await this._transaction.commit();
          console.log('✅ Transação commitada');
        }
      },
      
      async rollback() {
        if (this._transaction) {
          await this._transaction.rollback();
          console.log('↩️  Transação revertida');
        }
      },
      
      release() {
        this._transaction = null;
      }
    };
  }
};

process.on('SIGINT', async () => {
  if (pool) {
    await pool.close();
    console.log('🔌 Pool de conexões fechado');
  }
  process.exit(0);
});

export default dbAdapter;