-- CloudPartner HUB Database Schema
-- Azure SQL Database (T-SQL)

-- Tabela de Parceiros
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'partners')
BEGIN
    CREATE TABLE partners (
        id NVARCHAR(36) PRIMARY KEY,
        company_name NVARCHAR(255) NOT NULL,
        contact_name NVARCHAR(255),
        contact_phone NVARCHAR(50),
        contact_role NVARCHAR(100),
        email NVARCHAR(255) NOT NULL UNIQUE,
        phone NVARCHAR(50),
        mpn_id NVARCHAR(50),
        is_microsoft_partner BIT DEFAULT 0,
        is_td_synnex_registered BIT DEFAULT 0,
        partner_type_interest NVARCHAR(100),
        selected_solution_area NVARCHAR(100),
        csp_revenue DECIMAL(15, 2),
        client_count INT,
        
        -- PCS Scores
        pcs_performance INT DEFAULT 0,
        pcs_skilling INT DEFAULT 0,
        pcs_customer_success INT DEFAULT 0,
        
        -- Journey tracking
        current_step INT DEFAULT 1,
        status NVARCHAR(50) DEFAULT 'In Progress',
        
        -- Timestamps
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE()
    );

    CREATE INDEX idx_email ON partners(email);
    CREATE INDEX idx_solution_area ON partners(selected_solution_area);
    CREATE INDEX idx_status ON partners(status);
    CREATE INDEX idx_created_at ON partners(created_at);
END
GO

-- Tabela de Certificações
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'certifications')
BEGIN
    CREATE TABLE certifications (
        id INT IDENTITY(1,1) PRIMARY KEY,
        partner_id NVARCHAR(36) NOT NULL,
        cert_code NVARCHAR(100) NOT NULL,
        quantity INT DEFAULT 0,
        
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE(),
        
        FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE
    );

    CREATE UNIQUE INDEX idx_partner_cert ON certifications(partner_id, cert_code);
    CREATE INDEX idx_partner_id ON certifications(partner_id);
END
GO

-- Tabela de Análises de IA
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ai_analyses')
BEGIN
    CREATE TABLE ai_analyses (
        id INT IDENTITY(1,1) PRIMARY KEY,
        partner_id NVARCHAR(36) NOT NULL,
        analysis_type NVARCHAR(50) NOT NULL, -- 'recommendation', 'action_plan', etc
        input_data NVARCHAR(MAX), -- JSON stored as string
        ai_response NVARCHAR(MAX),
        
        created_at DATETIME2 DEFAULT GETDATE(),
        
        FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE
    );

    CREATE INDEX idx_partner_id_ai ON ai_analyses(partner_id);
    CREATE INDEX idx_created_at_ai ON ai_analyses(created_at);
END
GO

-- Tabela de Atividades GTM (Go-to-Market)
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'gtm_activities')
BEGIN
    CREATE TABLE gtm_activities (
        id INT IDENTITY(1,1) PRIMARY KEY,
        partner_id NVARCHAR(36) NOT NULL,
        category NVARCHAR(50) NOT NULL, -- 'Digital', 'Eventos', 'Capacitação'
        focus NVARCHAR(100) NOT NULL,
        name NVARCHAR(255) NOT NULL,
        investment DECIMAL(15, 2) DEFAULT 0,
        roi_multiplier DECIMAL(5, 2) DEFAULT 0,
        pipeline_generated DECIMAL(15, 2) DEFAULT 0,
        
        created_at DATETIME2 DEFAULT GETDATE(),
        
        FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE
    );

    CREATE INDEX idx_partner_id_gtm ON gtm_activities(partner_id);
END
GO

-- Trigger para atualizar updated_at na tabela partners
IF EXISTS (SELECT * FROM sys.triggers WHERE name = 'trg_partners_updated_at')
    DROP TRIGGER trg_partners_updated_at;
GO

CREATE TRIGGER trg_partners_updated_at
ON partners
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE partners
    SET updated_at = GETDATE()
    FROM partners p
    INNER JOIN inserted i ON p.id = i.id;
END
GO

-- Trigger para atualizar updated_at na tabela certifications
IF EXISTS (SELECT * FROM sys.triggers WHERE name = 'trg_certifications_updated_at')
    DROP TRIGGER trg_certifications_updated_at;
GO

CREATE TRIGGER trg_certifications_updated_at
ON certifications
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE certifications
    SET updated_at = GETDATE()
    FROM certifications c
    INNER JOIN inserted i ON c.id = i.id;
END
GO

-- View para relatórios (substitui CREATE OR REPLACE VIEW do MySQL)
IF EXISTS (SELECT * FROM sys.views WHERE name = 'partner_summary')
    DROP VIEW partner_summary;
GO

CREATE VIEW partner_summary AS
SELECT 
    p.id,
    p.company_name,
    p.email,
    p.selected_solution_area,
    (p.pcs_performance + p.pcs_skilling + p.pcs_customer_success) as total_pcs,
    p.current_step,
    p.status,
    p.created_at,
    COUNT(DISTINCT c.id) as total_certifications,
    SUM(c.quantity) as total_certified_users,
    COUNT(DISTINCT g.id) as total_gtm_activities
FROM partners p
LEFT JOIN certifications c ON p.id = c.partner_id
LEFT JOIN gtm_activities g ON p.id = g.partner_id
GROUP BY 
    p.id, 
    p.company_name, 
    p.email, 
    p.selected_solution_area,
    p.pcs_performance,
    p.pcs_skilling,
    p.pcs_customer_success,
    p.current_step,
    p.status,
    p.created_at;
GO

PRINT 'CloudPartner HUB Schema completo criado com sucesso para Azure SQL Database!';
PRINT 'Tabelas criadas: partners, certifications, ai_analyses, gtm_activities';
PRINT 'View criada: partner_summary';
