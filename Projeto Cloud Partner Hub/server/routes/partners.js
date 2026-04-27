import express from 'express';
import pool from '../config/database.js';

const router = express.Router();

// GET - Listar todos os parceiros
router.get('/', async (req, res) => {
  try {
    const [rows] = await pool.query(`
      SELECT 
        p.*,
        (p.pcs_performance + p.pcs_skilling + p.pcs_customer_success) as total_pcs
      FROM partners p
      ORDER BY p.created_at DESC
    `);
    res.json(rows);
  } catch (error) {
    console.error('Erro ao buscar parceiros:', error);
    res.status(500).json({ error: 'Erro ao buscar parceiros' });
  }
});

// GET - Buscar parceiro por ID
router.get('/:id', async (req, res) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM partners WHERE id = ?',
      [req.params.id]
    );
    
    if (rows.length === 0) {
      return res.status(404).json({ error: 'Parceiro não encontrado' });
    }
    
    // Buscar certificações
    const [certs] = await pool.query(
      'SELECT cert_code, quantity FROM certifications WHERE partner_id = ?',
      [req.params.id]
    );
    
    const partner = rows[0];
    partner.certifications = {};
    certs.forEach(cert => {
      partner.certifications[cert.cert_code] = cert.quantity;
    });
    
    res.json(partner);
  } catch (error) {
    console.error('Erro ao buscar parceiro:', error);
    res.status(500).json({ error: 'Erro ao buscar parceiro' });
  }
});

// POST - Criar novo parceiro
router.post('/', async (req, res) => {
  const connection = await pool.getConnection();
  
  try {
    await connection.beginTransaction();
    
    const {
      id,
      companyName,
      contactName,
      contactPhone,
      contactRole,
      email,
      phone,
      mpnId,
      isMicrosoftPartner,
      isTdSynnexRegistered,
      partnerTypeInterest,
      selectedSolutionArea,
      cspRevenue,
      clientCount,
      pcsPerformance,
      pcsSkilling,
      pcsCustomerSuccess,
      currentStep,
      status,
      certifications
    } = req.body;
    
    // Inserir parceiro
    await connection.query(
      `INSERT INTO partners (
        id, company_name, contact_name, contact_phone, contact_role, email, phone, mpn_id,
        is_microsoft_partner, is_td_synnex_registered, partner_type_interest,
        selected_solution_area, csp_revenue, client_count,
        pcs_performance, pcs_skilling, pcs_customer_success,
        current_step, status
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        id || `partner_${Date.now()}`,
        companyName,
        contactName,
        contactPhone || phone,
        contactRole,
        email,
        phone || contactPhone,
        mpnId,
        isMicrosoftPartner || false,
        isTdSynnexRegistered || false,
        partnerTypeInterest,
        selectedSolutionArea,
        cspRevenue || 0,
        clientCount || 0,
        pcsPerformance || 0,
        pcsSkilling || 0,
        pcsCustomerSuccess || 0,
        currentStep || 1,
        status || 'In Progress'
      ]
    );
    
    // Inserir certificações
    if (certifications && Object.keys(certifications).length > 0) {
      for (const [code, qty] of Object.entries(certifications)) {
        await connection.query(
          'INSERT INTO certifications (partner_id, cert_code, quantity) VALUES (?, ?, ?)',
          [id || `partner_${Date.now()}`, code, qty]
        );
      }
    }
    
    await connection.commit();
    res.status(201).json({ id, message: 'Parceiro criado com sucesso' });
  } catch (error) {
    await connection.rollback();
    console.error('Erro ao criar parceiro:', error);
    
    if (error.code === 'ER_DUP_ENTRY') {
      res.status(400).json({ error: 'Email já cadastrado' });
    } else {
      res.status(500).json({ error: 'Erro ao criar parceiro' });
    }
  } finally {
    connection.release();
  }
});

// PUT - Atualizar parceiro
router.put('/:id', async (req, res) => {
  const connection = await pool.getConnection();
  
  try {
    await connection.beginTransaction();
    
    const {
      companyName,
      contactName,
      contactPhone,
      contactRole,
      email,
      phone,
      mpnId,
      isMicrosoftPartner,
      isTdSynnexRegistered,
      partnerTypeInterest,
      selectedSolutionArea,
      cspRevenue,
      clientCount,
      pcsPerformance,
      pcsSkilling,
      pcsCustomerSuccess,
      currentStep,
      status,
      certifications
    } = req.body;
    
    // Validar e converter valores - garantir que undefined vira null
    const validPcsPerformance = parseInt(pcsPerformance) || 0;
    const validPcsSkilling = parseInt(pcsSkilling) || 0;
    const validPcsCustomerSuccess = parseInt(pcsCustomerSuccess) || 0;
    const validCspRevenue = parseFloat(cspRevenue) || 0;
    const validClientCount = parseInt(clientCount) || 0;
    const validCurrentStep = parseInt(currentStep) || 1;
    const validContactPhone = contactPhone || phone || null;
    const validContactRole = contactRole || null;
    const validCompanyName = companyName || null;
    const validContactName = contactName || null;
    const validEmail = email || null;
    const validMpnId = mpnId || null;
    const validIsMicrosoftPartner = isMicrosoftPartner === true || isMicrosoftPartner === 'true' ? true : false;
    const validIsTdSynnexRegistered = isTdSynnexRegistered === true || isTdSynnexRegistered === 'true' ? true : false;
    const validPartnerTypeInterest = partnerTypeInterest || null;
    const validSelectedSolutionArea = selectedSolutionArea || null;
    const validStatus = status || 'In Progress';

    // Verificar se o parceiro existe (por ID ou email)
    const [existingById] = await connection.query(
      'SELECT id FROM partners WHERE id = ?',
      [req.params.id]
    );
    
    const [existingByEmail] = await connection.query(
      'SELECT id FROM partners WHERE email = ?',
      [email]
    );

    // Determinar qual ID usar para o update
    let updateId = req.params.id;
    
    if (existingById && existingById.length > 0) {
      // Parceiro existe com o ID fornecido
      updateId = req.params.id;
    } else if (existingByEmail && existingByEmail.length > 0) {
      // Email já existe - usar o ID do banco ao invés do fornecido
      updateId = existingByEmail[0].id || existingByEmail[0].ID;
      console.log(`⚠️  Usando ID existente do banco: ${updateId} (ID fornecido era ${req.params.id})`);
    }

    if ((existingById && existingById.length > 0) || (existingByEmail && existingByEmail.length > 0)) {
      // Parceiro existe - fazer UPDATE
      await connection.query(
        `UPDATE partners SET
          company_name = ?,
          contact_name = ?,
          contact_phone = ?,
          contact_role = ?,
          email = ?,
          phone = ?,
          mpn_id = ?,
          is_microsoft_partner = ?,
          is_td_synnex_registered = ?,
          partner_type_interest = ?,
          selected_solution_area = ?,
          csp_revenue = ?,
          client_count = ?,
          pcs_performance = ?,
          pcs_skilling = ?,
          pcs_customer_success = ?,
          current_step = ?,
          status = ?
        WHERE id = ?`,
        [
          validCompanyName,
          validContactName,
          validContactPhone,
          validContactRole,
          validEmail,
          validContactPhone,
          validMpnId,
          validIsMicrosoftPartner,
          validIsTdSynnexRegistered,
          validPartnerTypeInterest,
          validSelectedSolutionArea,
          validCspRevenue,
          validClientCount,
          validPcsPerformance,
          validPcsSkilling,
          validPcsCustomerSuccess,
          validCurrentStep,
          validStatus,
          updateId
        ]
      );
    } else {
      // Parceiro não existe - criar novo
      await connection.query(
        `INSERT INTO partners (
          id, company_name, contact_name, contact_phone, contact_role, email, phone, mpn_id,
          is_microsoft_partner, is_td_synnex_registered, partner_type_interest,
          selected_solution_area, csp_revenue, client_count,
          pcs_performance, pcs_skilling, pcs_customer_success,
          current_step, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          req.params.id,
          validCompanyName,
          validContactName,
          validContactPhone,
          validContactRole,
          validEmail,
          validContactPhone,
          validMpnId,
          validIsMicrosoftPartner,
          validIsTdSynnexRegistered,
          validPartnerTypeInterest,
          validSelectedSolutionArea,
          validCspRevenue,
          validClientCount,
          validPcsPerformance,
          validPcsSkilling,
          validPcsCustomerSuccess,
          validCurrentStep,
          validStatus
        ]
      );
    }
    
    // Atualizar certificações (deletar e reinserir) - usar o updateId correto
    if (certifications) {
      await connection.query(
        'DELETE FROM certifications WHERE partner_id = ?',
        [updateId]
      );
      
      if (Object.keys(certifications).length > 0) {
        for (const [code, qty] of Object.entries(certifications)) {
          await connection.query(
            'INSERT INTO certifications (partner_id, cert_code, quantity) VALUES (?, ?, ?)',
            [updateId, code, qty]
          );
        }
      }
    }
    
    await connection.commit();
    res.json({ message: 'Parceiro atualizado com sucesso', id: updateId });
  } catch (error) {
    await connection.rollback();
    console.error('Erro ao atualizar parceiro:', error);
    console.error('Detalhes do erro:', error.message);
    console.error('Dados recebidos:', req.body);
    res.status(500).json({ 
      error: 'Erro ao atualizar parceiro',
      details: error.message 
    });
  } finally {
    connection.release();
  }
});

// DELETE - Remover parceiro
router.delete('/:id', async (req, res) => {
  try {
    await pool.query('DELETE FROM partners WHERE id = ?', [req.params.id]);
    res.json({ message: 'Parceiro removido com sucesso' });
  } catch (error) {
    console.error('Erro ao remover parceiro:', error);
    res.status(500).json({ error: 'Erro ao remover parceiro' });
  }
});

export default router;
