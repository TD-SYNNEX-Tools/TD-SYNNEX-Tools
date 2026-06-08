-- ============================================================================
-- Portal de Governança T2 — Schema (Azure SQL / T-SQL)
-- ----------------------------------------------------------------------------
-- Modelo enxuto: METADADOS + KPIs no SQL; arquivos brutos no Blob Storage.
--
-- Tabelas:
--   companies      empresa parceira T2 (1 empresa : N usuários)
--   users          usuário (Entra B2B Guest) vinculado a uma company + role
--   proposals      proposta salva explicitamente (metadados + KPIs resumidos)
--   usage_events   registro leve de TODA execução de análise (métrica de adoção)
--
-- Princípios:
--   - Acesso sempre filtrado por company_id do token (anti-OWASP A01).
--   - proposals guarda apenas resumo; arquivo pesado vai para blob_path.
--   - Sem dados pessoais de clientes finais (só razão social + CNPJ opcional).
-- ============================================================================

-- ---------------------------------------------------------------------------
-- COMPANIES — empresa parceira T2
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'companies')
BEGIN
    CREATE TABLE companies (
        id            NVARCHAR(36)  NOT NULL PRIMARY KEY,   -- UUID
        name          NVARCHAR(255) NOT NULL,
        tax_id        NVARCHAR(20)  NULL,                   -- CNPJ da empresa parceira (opcional)
        email_domain  NVARCHAR(255) NULL,                   -- ex.: parceiro.com.br (auto-vínculo)
        status        NVARCHAR(20)  NOT NULL DEFAULT 'active', -- active | suspended
        created_at    DATETIME2     NOT NULL DEFAULT GETDATE(),
        updated_at    DATETIME2     NOT NULL DEFAULT GETDATE()
    );

    CREATE UNIQUE INDEX ux_companies_email_domain
        ON companies(email_domain)
        WHERE email_domain IS NOT NULL;
    CREATE INDEX idx_companies_status ON companies(status);
END
GO

-- ---------------------------------------------------------------------------
-- USERS — pessoa que loga via Entra (B2B Guest), pertence a uma company
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'users')
BEGIN
    CREATE TABLE users (
        id          NVARCHAR(36)  NOT NULL PRIMARY KEY,     -- UUID
        company_id  NVARCHAR(36)  NULL,                     -- NULL enquanto cadastro pendente
        entra_oid   NVARCHAR(64)  NULL,                     -- object id (oid) do token Entra
        email       NVARCHAR(255) NOT NULL,
        name        NVARCHAR(255) NULL,
        role        NVARCHAR(20)  NOT NULL DEFAULT 'partner', -- partner | provider
        status      NVARCHAR(20)  NOT NULL DEFAULT 'pending', -- pending | active | rejected | disabled
        created_at  DATETIME2     NOT NULL DEFAULT GETDATE(),
        updated_at  DATETIME2     NOT NULL DEFAULT GETDATE(),

        CONSTRAINT fk_users_company
            FOREIGN KEY (company_id) REFERENCES companies(id)
    );

    CREATE UNIQUE INDEX ux_users_email ON users(email);
    CREATE UNIQUE INDEX ux_users_entra_oid
        ON users(entra_oid)
        WHERE entra_oid IS NOT NULL;
    CREATE INDEX idx_users_company ON users(company_id);
    CREATE INDEX idx_users_status  ON users(status);
END
GO

-- ---------------------------------------------------------------------------
-- PROPOSALS — proposta salva (metadados + KPIs resumidos). Arquivo no Blob.
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'proposals')
BEGIN
    CREATE TABLE proposals (
        id               NVARCHAR(36)   NOT NULL PRIMARY KEY,  -- UUID
        company_id       NVARCHAR(36)   NOT NULL,
        created_by       NVARCHAR(36)   NOT NULL,              -- users.id
        analysis_type    NVARCHAR(30)   NOT NULL,              -- financial | sql
        title            NVARCHAR(255)  NOT NULL,

        -- Cliente final (sem dados pessoais)
        customer_name    NVARCHAR(255)  NULL,
        customer_tax_id  NVARCHAR(20)   NULL,                  -- CNPJ opcional

        total_value      DECIMAL(15, 2) NULL,                  -- valor estimado do negócio
        status           NVARCHAR(20)   NOT NULL DEFAULT 'draft', -- draft | submitted | won | lost
        closed_via       NVARCHAR(30)   NULL,                  -- td_synnex | other_distributor | NULL

        blob_path        NVARCHAR(512)  NULL,                  -- caminho do arquivo no Blob
        result_summary   NVARCHAR(MAX)  NULL,                  -- JSON com KPIs (string)

        created_at       DATETIME2      NOT NULL DEFAULT GETDATE(),
        updated_at       DATETIME2      NOT NULL DEFAULT GETDATE(),

        CONSTRAINT fk_proposals_company
            FOREIGN KEY (company_id) REFERENCES companies(id),
        CONSTRAINT fk_proposals_user
            FOREIGN KEY (created_by) REFERENCES users(id),
        CONSTRAINT ck_proposals_analysis_type
            CHECK (analysis_type IN ('financial', 'sql')),
        CONSTRAINT ck_proposals_status
            CHECK (status IN ('draft', 'submitted', 'won', 'lost')),
        CONSTRAINT ck_proposals_closed_via
            CHECK (closed_via IS NULL OR closed_via IN ('td_synnex', 'other_distributor'))
    );

    CREATE INDEX idx_proposals_company    ON proposals(company_id);
    CREATE INDEX idx_proposals_created_by ON proposals(created_by);
    CREATE INDEX idx_proposals_status     ON proposals(status);
    CREATE INDEX idx_proposals_closed_via ON proposals(closed_via);
    CREATE INDEX idx_proposals_created_at ON proposals(created_at);
END
GO

-- ---------------------------------------------------------------------------
-- USAGE_EVENTS — linha leve gravada em TODA execução de análise (adoção)
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'usage_events')
BEGIN
    CREATE TABLE usage_events (
        id             BIGINT IDENTITY(1,1) PRIMARY KEY,
        company_id     NVARCHAR(36)  NULL,
        user_id        NVARCHAR(36)  NULL,
        analysis_type  NVARCHAR(30)  NOT NULL,                 -- financial | sql
        created_at     DATETIME2     NOT NULL DEFAULT GETDATE()
    );

    CREATE INDEX idx_usage_company    ON usage_events(company_id);
    CREATE INDEX idx_usage_created_at ON usage_events(created_at);
END
GO

-- ---------------------------------------------------------------------------
-- Triggers updated_at
-- ---------------------------------------------------------------------------
IF EXISTS (SELECT * FROM sys.triggers WHERE name = 'trg_companies_updated_at')
    DROP TRIGGER trg_companies_updated_at;
GO
CREATE TRIGGER trg_companies_updated_at
ON companies
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE companies
    SET updated_at = GETDATE()
    FROM companies c
    INNER JOIN inserted i ON c.id = i.id;
END
GO

IF EXISTS (SELECT * FROM sys.triggers WHERE name = 'trg_users_updated_at')
    DROP TRIGGER trg_users_updated_at;
GO
CREATE TRIGGER trg_users_updated_at
ON users
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE users
    SET updated_at = GETDATE()
    FROM users u
    INNER JOIN inserted i ON u.id = i.id;
END
GO

IF EXISTS (SELECT * FROM sys.triggers WHERE name = 'trg_proposals_updated_at')
    DROP TRIGGER trg_proposals_updated_at;
GO
CREATE TRIGGER trg_proposals_updated_at
ON proposals
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE proposals
    SET updated_at = GETDATE()
    FROM proposals p
    INNER JOIN inserted i ON p.id = i.id;
END
GO

-- ---------------------------------------------------------------------------
-- View executiva (base da Fase 2) — agregados por empresa parceira
-- ---------------------------------------------------------------------------
IF EXISTS (SELECT * FROM sys.views WHERE name = 'company_governance_summary')
    DROP VIEW company_governance_summary;
GO
CREATE VIEW company_governance_summary AS
SELECT
    c.id                         AS company_id,
    c.name                       AS company_name,
    COUNT(DISTINCT p.id)         AS total_proposals,
    SUM(CASE WHEN p.closed_via = 'td_synnex'          THEN 1 ELSE 0 END) AS won_td_synnex,
    SUM(CASE WHEN p.closed_via = 'other_distributor'  THEN 1 ELSE 0 END) AS won_other,
    SUM(CASE WHEN p.status = 'won'  THEN p.total_value ELSE 0 END)       AS won_value,
    MAX(p.created_at)            AS last_proposal_at
FROM companies c
LEFT JOIN proposals p ON p.company_id = c.id
GROUP BY c.id, c.name;
GO

PRINT 'Schema de governança T2 criado: companies, users, proposals, usage_events + view company_governance_summary';
