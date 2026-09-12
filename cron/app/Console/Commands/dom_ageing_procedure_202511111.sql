CREATE DEFINER=`graphitebwlive`@`%` PROCEDURE `temp_summary_age_analyst_dom`(in fd VARCHAR(110))
BEGIN
	DECLARE finished INTEGER DEFAULT 0;
	DECLARE temp_balance_outstanding VARCHAR(120);
	DECLARE temp_days_30 VARCHAR(120);
	DECLARE temp_days_60 VARCHAR(120);
	DECLARE temp_days_90 VARCHAR(120);
	DECLARE temp_days_120 VARCHAR(120);
	DECLARE invoice_days_30 VARCHAR(120);
	DECLARE invoice_days_60 VARCHAR(120);
	DECLARE invoice_days_90 VARCHAR(120);
	DECLARE invoice_days_120 VARCHAR(120);
	DECLARE invoice_days_debit_120 VARCHAR(120);
	DECLARE invoice_days_credit_120 VARCHAR(120);

	DECLARE invoice_days_invoice_amount_120 VARCHAR(120);
	DECLARE invoice_days_amount_120 VARCHAR(120);

	DECLARE invoice_days_credit_90 VARCHAR(120);
	DECLARE invoice_days_debit_90 VARCHAR(120);

	DECLARE invoice_days_credit_60 VARCHAR(120);
	DECLARE invoice_days_debit_60 VARCHAR(120);

	DECLARE invoice_days_credit_30 VARCHAR(120);
	DECLARE invoice_days_debit_30 VARCHAR(120);

	DECLARE payment_days_30 VARCHAR(120);
	DECLARE payment_days_60 VARCHAR(120);
	DECLARE payment_days_90 VARCHAR(120);
	DECLARE payment_days_120 VARCHAR(120);
	DECLARE refund_days_30 VARCHAR(120);
	DECLARE refund_days_60 VARCHAR(120);
	DECLARE refund_days_90 VARCHAR(120);
	DECLARE refund_days_120 VARCHAR(120);
	DECLARE archive_invoice_days_30 VARCHAR(120);
	DECLARE archive_invoice_days_60 VARCHAR(120);
	DECLARE archive_invoice_days_90 VARCHAR(120);
	DECLARE archive_invoice_days_120 VARCHAR(120);
	DECLARE archive_payment_days_30 VARCHAR(120);
	DECLARE archive_payment_days_60 VARCHAR(120);
	DECLARE archive_payment_days_90 VARCHAR(120);
	DECLARE archive_payment_days_120 VARCHAR(120);
	DECLARE archive_refund_days_30 VARCHAR(120);
	DECLARE archive_refund_days_60 VARCHAR(120);
	DECLARE archive_refund_days_90 VARCHAR(120);
	DECLARE archive_refund_days_120 VARCHAR(120);
	DECLARE xid int(11);
	DECLARE xpolicyNumber VARCHAR(120);
	DECLARE xclient_name VARCHAR(220);
	DECLARE xagent_name VARCHAR(220);
	DECLARE xproduct_name VARCHAR(220);
	DECLARE xpolicyStatus VARCHAR(220);
	DECLARE xfrequency VARCHAR(220);
	DECLARE xinvoiceTotal VARCHAR(220);
	DECLARE xpaymentTotal VARCHAR(220);
	DECLARE xrefundTotal VARCHAR(220);
	DECLARE archive_xinvoiceTotal VARCHAR(220);
	DECLARE archive_xpaymentTotal VARCHAR(220);
	DECLARE archive_xrefundTotal VARCHAR(220);
	DECLARE temp_xinvoiceTotal VARCHAR(220);
	DECLARE temp_xpaymentTotal VARCHAR(220);
	DECLARE temp_xrefundTotal VARCHAR(220);
	DECLARE xCreated VARCHAR(220);
    DECLARE xCoverageCode VARCHAR(220);
	DECLARE po CURSOR FOR SELECT p.id,p.policyNumber,p.status,
	-- concat(ifnull(c.firstName, ""), " ", ifnull(c.lastName, "")) as client_name,
    CASE 
    WHEN IFNULL(com.name, '') <> '' 
        THEN com.name
    ELSE CONCAT(IFNULL(c.firstName, ''), ' ', IFNULL(c.lastName, ''))
   END AS client_name,
	pr.`name`,
	CASE
	    WHEN p.status=1 THEN "Activated"
	    WHEN p.status=2 THEN "Cancelled"
	    WHEN p.status=0 THEN "Deactivated"
	    ELSE ""
	END as status,
	CASE
	        WHEN p.premium_freq=1 THEN "Monthly"
			WHEN p.premium_freq=2 THEN "3 installments"
			WHEN p.premium_freq=3 THEN "Annual"
			WHEN p.premium_freq=4 THEN "Semiannual"        
			ELSE "Quarterly"
	END as frequency,
    -- concat(ifnull(c.firstName, ""), " ", ifnull(c.lastName, "")) as agent_name,
    -- CASE
	--	WHEN p.agent_id IS NULL OR p.agent_id = 0 THEN NULL
	--	ELSE CONCAT(IFNULL(u.firstName, ''), ' ', IFNULL(u.lastName, ''))
	-- END AS agent_name,
    IFNULL(u.name, '')  AS agent_name,
    p.created_at,
    tb_cvgpccoverages.s_CoverageCode as coverage_code
	FROM FY_2425_Ageing as pag
	Inner join policies as p on p.id= pag.policy_id
	INNER JOIN risk_address ON risk_address.policy_id = p.id
	INNER JOIN policy_coverages ON policy_coverages.risk_address_id = risk_address.id
	INNER JOIN policy_actions ON policy_actions.id = policy_coverages.action_id AND policy_actions.policy_id = policy_coverages.policy_id
	left JOIN tb_cvgpccoverages ON tb_cvgpccoverages.id = policy_coverages.coverage_id
    left join products as pr on pr.id = p.product_id
    left join customer as c on c.id = p.customer_id
    left join agencies as u on u.id = p.agency_id
    left join companies as com on com.id = c.company_id
	-- left join policy_ledger as pl on policy_actions.policy_id = pl.policy_id
    #left join policy_actions as pa on pa.policy_id = p.id
    left join customer_profile as cust on cust.customer_id = c.id
	-- inner join users as u on u.id = p.agent_id
	-- inner join companies as com on com.id = c.company_id
	WHERE date(p.created_at) >= '2024-07-01'
	-- and p.created_at <= fd
    and policy_actions.effective_from <= fd
    # and pl.accounting_date <= fd
	AND p.is_test_policy = 0
     -- AND p.id  IN (99561)
     AND policy_actions.status = 'ISSUED'
   -- AND policy_actions.transaction_type !=  'CANCEL'
    and pr.id IN (8)
	-- AND p.status != '2'
    -- and p.status = 1
   -- and c.company_id IS NULL
    group by p.id;
    
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;
	
	/*Delete All Previous data*/
	-- truncate summary_age_analyst_report_dom_com;
	
open po;
       getPo: LOOP
       	 	FETCH FROM po INTO xid,xpolicyNumber,xpolicyStatus,xclient_name,xproduct_name,xpolicyStatus,xfrequency,xagent_name,xCreated,xCoverageCode;
       	 	IF finished = 1 THEN
               LEAVE getPo;
           	END IF;
           	
           	-- Initialize aging variables
           	set temp_balance_outstanding=0;
			set temp_days_30=0;
			set temp_days_60=0;
			set temp_days_90=0;
			set temp_days_120=0;
 
			-- CORRECTED AGING CALCULATIONS TO MATCH OUTSTANDING BALANCE LOGIC
			
			-- 30-DAY BUCKET - Match outstanding balance logic exactly
			SELECT IFNULL(SUM(invoice_amount), 0)
			INTO temp_days_30
			FROM policy_ledger pl
			LEFT JOIN credit_note cn ON pl.invoice_no COLLATE utf8mb4_general_ci = cn.invoice_no
			WHERE pl.policy_id = xid
			AND pl.trans_type = 'Invoice'
			AND pl.status != 'Reversed'
			#AND pl.deleted_at IS NULL
			AND DATE(pl.invoice_date) <= fd
            AND DATE(pl.accounting_date) <= '2025-08-23'
            AND ( date(pl.deleted_at) >= '2025-08-23' or pl.deleted_at is null)
			AND DATEDIFF(fd, DATE(pl.invoice_date)) BETWEEN 0 AND 30
			AND cn.invoice_no IS NULL;
 
			-- Subtract payments for 30-day bucket  
			SET @payments_30 = 0;
			SELECT IFNULL(SUM(amount), 0)
			INTO @payments_30
			FROM payment_transactions
			WHERE policyNumber = xpolicyNumber
			#AND deleted_at IS NULL
			AND status = "Success"
			AND CompanyRef IS NULL
			AND amount != 1
			AND is_refund = 0
			AND DATE(paymentDate) <= fd
            and created_at  <= '2025-08-23'  and ( date(deleted_at) >= '2025-08-23' or deleted_at is null) 
			AND DATEDIFF(fd, DATE(paymentDate)) BETWEEN 0 AND 30;
 
			SET temp_days_30 = temp_days_30 - @payments_30;
 
			-- Add archive data for 30-day bucket
			SET @archive_30 = 0;
			SELECT IFNULL(SUM(invoice_amount), 0)
			INTO @archive_30
			FROM graphite_archive.policy_ledger
			WHERE policy_id = xid
			AND trans_type = "Invoice"
			AND status != 'Reversed'
			AND DATE(invoice_date) <= fd
			AND DATEDIFF(fd, DATE(invoice_date)) BETWEEN 0 AND 30;
 
			-- Archive payments for 30-day
			SET @archive_payments_30 = 0;
			SELECT IFNULL(SUM(amount), 0)
			INTO @archive_payments_30
			FROM graphite_archive.payment_transactions
			WHERE policyNumber = xpolicyNumber
			AND deleted_at IS NULL
			AND status = "Success"
			AND amount != 1
			AND is_refund = 0
			AND DATE(paymentDate) <= fd
			AND DATEDIFF(fd, DATE(paymentDate)) BETWEEN 0 AND 30;
 
			SET temp_days_30 = temp_days_30 + @archive_30 - @archive_payments_30;
 
			-- 60-DAY BUCKET
			SELECT IFNULL(SUM(invoice_amount), 0)
			INTO temp_days_60
			FROM policy_ledger pl
			LEFT JOIN credit_note cn ON pl.invoice_no COLLATE utf8mb4_general_ci = cn.invoice_no
			WHERE pl.policy_id = xid
			AND pl.trans_type = 'Invoice'
			AND pl.status != 'Reversed'
			#AND pl.deleted_at IS NULL
			AND DATE(pl.invoice_date) <= fd
            AND DATE(pl.accounting_date) <= '2025-08-23'
            AND ( date(pl.deleted_at) >= '2025-08-23' or pl.deleted_at is null)
			AND DATEDIFF(fd, DATE(pl.invoice_date)) BETWEEN 31 AND 60
			AND cn.invoice_no IS NULL;
 
			SET @payments_60 = 0;
			SELECT IFNULL(SUM(amount), 0)
			INTO @payments_60
			FROM payment_transactions
			WHERE policyNumber = xpolicyNumber
			#AND deleted_at IS NULL
			AND status = "Success"
			AND CompanyRef IS NULL
			AND amount != 1
			AND is_refund = 0
			AND DATE(paymentDate) <= fd
            and created_at  <= '2025-08-23'  and ( date(deleted_at) >= '2025-08-23' or deleted_at is null) 
			AND DATEDIFF(fd, DATE(paymentDate)) BETWEEN 31 AND 60;
 
			SET temp_days_60 = temp_days_60 - @payments_60;
 
			-- Archive for 60-day
			SET @archive_60 = 0;
			SELECT IFNULL(SUM(invoice_amount), 0)
			INTO @archive_60
			FROM graphite_archive.policy_ledger
			WHERE policy_id = xid
			AND trans_type = "Invoice"
			AND status != 'Reversed'
			AND DATE(invoice_date) <= fd
			AND DATEDIFF(fd, DATE(invoice_date)) BETWEEN 31 AND 60;
 
			SET @archive_payments_60 = 0;
			SELECT IFNULL(SUM(amount), 0)
			INTO @archive_payments_60
			FROM graphite_archive.payment_transactions
			WHERE policyNumber = xpolicyNumber
			AND deleted_at IS NULL
			AND status = "Success"
			AND amount != 1
			AND is_refund = 0
			AND DATE(paymentDate) <= fd
			AND DATEDIFF(fd, DATE(paymentDate)) BETWEEN 31 AND 60;
 
			SET temp_days_60 = temp_days_60 + @archive_60 - @archive_payments_60;
 
			-- 90-DAY BUCKET
			SELECT IFNULL(SUM(invoice_amount), 0)
			INTO temp_days_90
			FROM policy_ledger pl
			LEFT JOIN credit_note cn ON pl.invoice_no COLLATE utf8mb4_general_ci = cn.invoice_no
			WHERE pl.policy_id = xid
			AND pl.trans_type = 'Invoice'
			AND pl.status != 'Reversed'
			#AND pl.deleted_at IS NULL
			AND DATE(pl.invoice_date) <= fd
            AND DATE(pl.accounting_date) <= '2025-08-23'
            AND ( date(pl.deleted_at) >= '2025-08-23' or pl.deleted_at is null)
			AND DATEDIFF(fd, DATE(pl.invoice_date)) BETWEEN 61 AND 90
			AND cn.invoice_no IS NULL;
 
			SET @payments_90 = 0;
			SELECT IFNULL(SUM(amount), 0)
			INTO @payments_90
			FROM payment_transactions
			WHERE policyNumber = xpolicyNumber
			#AND deleted_at IS NULL
			AND status = "Success"
			AND CompanyRef IS NULL
			AND amount != 1
			AND is_refund = 0
			AND DATE(paymentDate) <= fd
            and created_at  <= '2025-08-23'  and ( date(deleted_at) >= '2025-08-23' or deleted_at is null) 
			AND DATEDIFF(fd, DATE(paymentDate)) BETWEEN 61 AND 90;
 
			SET temp_days_90 = temp_days_90 - @payments_90;
 
			-- Archive for 90-day
			SET @archive_90 = 0;
			SELECT IFNULL(SUM(invoice_amount), 0)
			INTO @archive_90
			FROM graphite_archive.policy_ledger
			WHERE policy_id = xid
			AND trans_type = "Invoice"
			AND status != 'Reversed'
			AND DATE(invoice_date) <= fd
			AND DATEDIFF(fd, DATE(invoice_date)) BETWEEN 61 AND 90;
 
			SET @archive_payments_90 = 0;
			SELECT IFNULL(SUM(amount), 0)
			INTO @archive_payments_90
			FROM graphite_archive.payment_transactions
			WHERE policyNumber = xpolicyNumber
			AND deleted_at IS NULL
			AND status = "Success"
			AND amount != 1
			AND is_refund = 0
			AND DATE(paymentDate) <= fd
			AND DATEDIFF(fd, DATE(paymentDate)) BETWEEN 61 AND 90;
 
			SET temp_days_90 = temp_days_90 + @archive_90 - @archive_payments_90;
 
			-- 120+ DAY BUCKET
			SELECT IFNULL(SUM(invoice_amount), 0)
			INTO temp_days_120
			FROM policy_ledger pl
			LEFT JOIN credit_note cn ON pl.invoice_no COLLATE utf8mb4_general_ci = cn.invoice_no
			WHERE pl.policy_id = xid
			AND pl.trans_type = 'Invoice'
			AND pl.status != 'Reversed'
			#AND pl.deleted_at IS NULL
			AND DATE(pl.invoice_date) <= fd
            AND DATE(pl.accounting_date) <= '2025-08-23'
            AND ( date(pl.deleted_at) >= '2025-08-23' or pl.deleted_at is null)
			AND DATEDIFF(fd, DATE(pl.invoice_date)) > 90
			AND cn.invoice_no IS NULL;
 
			SET @payments_120 = 0;
			SELECT IFNULL(SUM(amount), 0)
			INTO @payments_120
			FROM payment_transactions
			WHERE policyNumber = xpolicyNumber
			#AND deleted_at IS NULL
			AND status = "Success"
			AND CompanyRef IS NULL
			AND amount != 1
			AND is_refund = 0
			AND DATE(paymentDate) <= fd
            and created_at  <= '2025-08-23'  and ( date(deleted_at) >= '2025-08-23' or deleted_at is null) 
			AND DATEDIFF(fd, DATE(paymentDate)) > 90;
 
			SET temp_days_120 = temp_days_120 - @payments_120;
 
			-- Archive for 120+ day
			SET @archive_120 = 0;
			SELECT IFNULL(SUM(invoice_amount), 0)
			INTO @archive_120
			FROM graphite_archive.policy_ledger
			WHERE policy_id = xid
			AND trans_type = "Invoice"
			AND status != 'Reversed'
			AND DATE(invoice_date) <= fd
			AND DATEDIFF(fd, DATE(invoice_date)) > 90;
 
			SET @archive_payments_120 = 0;
			SELECT IFNULL(SUM(amount), 0)
			INTO @archive_payments_120
			FROM graphite_archive.payment_transactions
			WHERE policyNumber = xpolicyNumber
			AND deleted_at IS NULL
			AND status = "Success"
			AND amount != 1
			AND is_refund = 0
			AND DATE(paymentDate) <= fd
			AND DATEDIFF(fd, DATE(paymentDate)) > 90;
 
			SET temp_days_120 = temp_days_120 + @archive_120 - @archive_payments_120;
 
			-- EXISTING ACCURATE OUTSTANDING BALANCE CALCULATION (UNCHANGED)
			set xinvoiceTotal=0;
			select if(sum(invoice_amount) is null, 0, sum(invoice_amount)) as b
			from policy_ledger pl
			LEFT JOIN credit_note cn ON pl.invoice_no COLLATE utf8mb4_general_ci = cn.invoice_no
			WHERE pl.policy_id = xid
			AND pl.trans_type = 'Invoice'
            AND pl.status != 'Reversed'
            # AND pl.deleted_at IS NULL
			AND DATE(pl.invoice_date) <= fd
            #AND DATE(pl.accounting_date) <= '2025-09-30'
			AND DATE(pl.accounting_date) <= '2025-08-23'
            AND ( date(pl.deleted_at) >= '2025-08-23' or pl.deleted_at is null)
			AND cn.invoice_no IS NULL
			into xinvoiceTotal;
 
			set xpaymentTotal=0;
			select sum(amount) as b from payment_transactions where policyNumber=xpolicyNumber  and created_at  <= '2025-08-23'  and ( date(deleted_at) >= '2025-08-23' or deleted_at is null) and status="Success" and CompanyRef IS NULL and amount != 1 and is_refund = 0 and date(paymentDate) <= fd into xpaymentTotal;
			set xrefundTotal=0;
			select if(sum(amount) is null,0,sum(amount)) as b from payment_transactions where policyNumber=xpolicyNumber  and deleted_at is null and status="Success" and CompanyRef = 'Reversed' and date(paymentDate) <= fd  into xrefundTotal;
			set xpaymentTotal = if(xpaymentTotal is null,0,xpaymentTotal);
			set xrefundTotal = if(xrefundTotal is null,0,xrefundTotal);
			set xinvoiceTotal = if(xinvoiceTotal is null,0,xinvoiceTotal);
 
			set archive_xinvoiceTotal=0;
			select if(sum(invoice_amount)is null,0,sum(invoice_amount)) as b from graphite_archive.policy_ledger where policy_id=xid and trans_type="Invoice" and status != 'Reversed' and date(invoice_date) <= fd into archive_xinvoiceTotal;
			set archive_xpaymentTotal=0;
			select sum(amount) as b from graphite_archive.payment_transactions where policyNumber=xpolicyNumber  and deleted_at is null and status="Success" and amount != 1 and is_refund = 0 and date(paymentDate) <= fd into archive_xpaymentTotal;
			set archive_xrefundTotal=0;
			select if(sum(amount) is null,0,sum(amount)) as b from graphite_archive.payment_transactions where policyNumber=xpolicyNumber  and deleted_at is null and status="Success" and is_refund = 1 and date(paymentDate) <= fd into archive_xrefundTotal;
			set archive_xpaymentTotal = if(archive_xpaymentTotal is null,0,archive_xpaymentTotal);
			set archive_xrefundTotal = if(archive_xrefundTotal is null,0,archive_xrefundTotal);
			set archive_xinvoiceTotal = if(archive_xinvoiceTotal is null,0,archive_xinvoiceTotal);
 
			SET temp_xinvoiceTotal = 0 ;
			SET temp_xpaymentTotal = 0 ;
			SET temp_xrefundTotal = 0 ;
			SET temp_xinvoiceTotal = CAST(xinvoiceTotal AS DECIMAL(16,2)) + CAST(archive_xinvoiceTotal AS DECIMAL(16,2));
			SET temp_xpaymentTotal = CAST(xpaymentTotal AS DECIMAL(16,2)) + CAST(archive_xpaymentTotal AS DECIMAL(16,2));
			SET temp_xrefundTotal = CAST(xrefundTotal AS DECIMAL(16,2)) + CAST(archive_xrefundTotal AS DECIMAL(16,2));
			
			-- KEEP EXISTING ACCURATE OUTSTANDING BALANCE CALCULATION
			SET temp_balance_outstanding = ((CAST(xinvoiceTotal AS DECIMAL(16,2)) + CAST(archive_xinvoiceTotal AS DECIMAL(16,2))) - (CAST(xpaymentTotal AS DECIMAL(16,2)) + CAST(archive_xpaymentTotal AS DECIMAL(16,2)))) ;
 
			SET finished = 0;
			IF temp_balance_outstanding = 0 THEN
					SET temp_days_30 = 0;
					SET temp_days_60 = 0;	
					SET temp_days_90=0;
					SET temp_days_120=0;
			END IF;
			IF xpolicyStatus = 'Cancelled'  THEN
				INSERT INTO temp_summary_age_analyst_report_dom_com (
					policy_id, policyNumber, client_name, product_name,
					balance_outstanding, 30_days, 60_days, 90_days, 120_days_and_above,
					policy_status, invoice_total, payment_total, refund_total,
					premium_freq, agent_name, created_at, from_date, policy_created_at, coverage_code
				)
				VALUES (
					xid, xpolicyNumber, xclient_name, xproduct_name,
					temp_balance_outstanding, temp_days_30, temp_days_60, temp_days_90, temp_days_120,
					xpolicyStatus, temp_xinvoiceTotal, temp_xpaymentTotal, temp_xrefundTotal,
					xfrequency, xagent_name, NOW(), fd, xCreated, xCoverageCode
				);
 
			ELSEIF xpolicyStatus = 'Deactivated'  THEN
				INSERT INTO temp_summary_age_analyst_report_dom_com (
					policy_id, policyNumber, client_name, product_name,
					balance_outstanding, 30_days, 60_days, 90_days, 120_days_and_above,
					policy_status, invoice_total, payment_total, refund_total,
					premium_freq, agent_name, created_at, from_date, policy_created_at, coverage_code
				)
				VALUES (
					xid, xpolicyNumber, xclient_name, xproduct_name,
					temp_balance_outstanding, temp_days_30, temp_days_60, temp_days_90, temp_days_120,
					xpolicyStatus, temp_xinvoiceTotal, temp_xpaymentTotal, temp_xrefundTotal,
					xfrequency, xagent_name, NOW(), fd, xCreated, xCoverageCode
				);
 
			ELSEIF xpolicyStatus = 'Activated' THEN
				INSERT INTO temp_summary_age_analyst_report_dom_com (
					policy_id, policyNumber, client_name, product_name,
					balance_outstanding, 30_days, 60_days, 90_days, 120_days_and_above,
					policy_status, invoice_total, payment_total, refund_total,
					premium_freq, agent_name, created_at, from_date, policy_created_at, coverage_code
				)
				VALUES (
					xid, xpolicyNumber, xclient_name, xproduct_name,
					temp_balance_outstanding, temp_days_30, temp_days_60, temp_days_90, temp_days_120,
					xpolicyStatus, temp_xinvoiceTotal, temp_xpaymentTotal, temp_xrefundTotal,
					xfrequency, xagent_name, NOW(), fd, xCreated, xCoverageCode
				);
			END IF;
 
		END LOOP;
	   CLOSE po;
END