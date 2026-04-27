-- CloudPartner HUB Database Schema
-- MySQL Database

CREATE DATABASE IF NOT EXISTS cloudpartner_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE cloudpartner_hub;

-- Tabela de Parceiros
CREATE TABLE IF NOT EXISTS partners (
    id VARCHAR(36) PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255),
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(50),
    mpn_id VARCHAR(50),
    is_microsoft_partner BOOLEAN DEFAULT FALSE,
    is_td_synnex_registered BOOLEAN DEFAULT FALSE,
    partner_type_interest VARCHAR(100),
    selected_solution_area VARCHAR(100),
    csp_revenue DECIMAL(15, 2),
    client_count INT,
    
    -- PCS Scores
    pcs_performance INT DEFAULT 0,
    pcs_skilling INT DEFAULT 0,
    pcs_customer_success INT DEFAULT 0,
    
    -- Journey tracking
    current_step INT DEFAULT 1,
    status ENUM('Not Started', 'In Progress', 'Completed', 'Stalled') DEFAULT 'In Progress',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_solution_area (selected_solution_area),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Certificações
CREATE TABLE IF NOT EXISTS certifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_id VARCHAR(36) NOT NULL,
    cert_code VARCHAR(100) NOT NULL,
    quantity INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
    UNIQUE KEY unique_partner_cert (partner_id, cert_code),
    INDEX idx_partner_id (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Análises de IA (Opcional)
CREATE TABLE IF NOT EXISTS ai_analyses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_id VARCHAR(36) NOT NULL,
    analysis_type VARCHAR(50) NOT NULL, -- 'recommendation', 'action_plan', etc
    input_data JSON,
    ai_response TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
    INDEX idx_partner_id (partner_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Atividades GTM (Go-to-Market)
CREATE TABLE IF NOT EXISTS gtm_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_id VARCHAR(36) NOT NULL,
    category VARCHAR(50) NOT NULL, -- 'Digital', 'Eventos', 'Capacitação'
    focus VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    investment DECIMAL(15, 2) DEFAULT 0,
    roi_multiplier DECIMAL(5, 2) DEFAULT 0,
    pipeline_generated DECIMAL(15, 2) DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
    INDEX idx_partner_id (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- View para relatórios
CREATE OR REPLACE VIEW partner_summary AS
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
GROUP BY p.id;
