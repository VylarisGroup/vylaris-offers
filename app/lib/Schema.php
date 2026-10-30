<?php
/**
 * Datenbankschema und Grunddaten. Wird von setup.php verwendet.
 */
declare(strict_types=1);

final class Schema
{
    public const VERSION = 15;

    public static function statements(): array
    {
        $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS settings (
                `key` VARCHAR(100) NOT NULL PRIMARY KEY,
                `value` MEDIUMTEXT NULL
            ) $t",

            "CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('admin','team') NOT NULL DEFAULT 'team',
                totp_secret VARCHAR(255) NULL,
                totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
                totp_last_step BIGINT NULL,
                recovery_codes TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                last_login_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                sso_issuer VARCHAR(255) NULL,
                sso_sub VARCHAR(190) NULL,
                changelog_seen VARCHAR(20) NULL,
                UNIQUE KEY uq_users_email (email)
            ) $t",

            "CREATE TABLE IF NOT EXISTS customers (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                customer_no VARCHAR(20) NOT NULL,
                salutation VARCHAR(20) NULL,
                first_name VARCHAR(100) NOT NULL DEFAULT '',
                last_name VARCHAR(100) NOT NULL DEFAULT '',
                company VARCHAR(190) NULL,
                street VARCHAR(190) NOT NULL DEFAULT '',
                address_extra VARCHAR(190) NULL,
                zip VARCHAR(20) NOT NULL DEFAULT '',
                city VARCHAR(120) NOT NULL DEFAULT '',
                country CHAR(2) NOT NULL DEFAULT 'CH',
                email VARCHAR(190) NOT NULL,
                phone VARCHAR(50) NULL,
                mobile VARCHAR(50) NULL,
                website VARCHAR(190) NULL,
                vat_id VARCHAR(50) NULL,
                tax_exempt TINYINT(1) NOT NULL DEFAULT 0,
                tax_exempt_reason VARCHAR(255) NULL,
                notes TEXT NULL,
                portal_enabled TINYINT(1) NOT NULL DEFAULT 1,
                last_portal_login_at DATETIME NULL,
                restricted_at DATETIME NULL,
                anonymized_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_customers_no (customer_no),
                UNIQUE KEY uq_customers_email (email),
                KEY idx_customers_name (last_name, company)
            ) $t",

            "CREATE TABLE IF NOT EXISTS login_tokens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                subject_type ENUM('user','customer') NOT NULL,
                subject_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_token_hash (token_hash),
                KEY idx_token_subject (subject_type, subject_id)
            ) $t",

            "CREATE TABLE IF NOT EXISTS rate_limits (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bucket VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_rl (bucket, created_at)
            ) $t",

            "CREATE TABLE IF NOT EXISTS product_areas (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                type ENUM('digital','physical') NOT NULL,
                description TEXT NULL,
                icon VARCHAR(60) NOT NULL DEFAULT 'bx-category',
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL
            ) $t",

            "CREATE TABLE IF NOT EXISTS tax_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                country CHAR(2) NULL,
                name VARCHAR(120) NOT NULL,
                label VARCHAR(40) NOT NULL,
                rate DECIMAL(6,3) NOT NULL DEFAULT 0,
                tax_class VARCHAR(16) NOT NULL DEFAULT 'taxable',
                valid_from DATE NULL,
                valid_to DATE NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort INT NOT NULL DEFAULT 0,
                KEY idx_tax_country (country)
            ) $t",

            "CREATE TABLE IF NOT EXISTS shipping_methods (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                description VARCHAR(255) NULL,
                cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort INT NOT NULL DEFAULT 0
            ) $t",

            "CREATE TABLE IF NOT EXISTS item_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_area_id INT UNSIGNED NULL,
                title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                unit VARCHAR(30) NOT NULL DEFAULT 'Stk.',
                unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
                tax_template_id INT UNSIGNED NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                CONSTRAINT fk_tpl_area FOREIGN KEY (product_area_id) REFERENCES product_areas(id) ON DELETE SET NULL,
                CONSTRAINT fk_tpl_tax FOREIGN KEY (tax_template_id) REFERENCES tax_templates(id) ON DELETE SET NULL
            ) $t",

            "CREATE TABLE IF NOT EXISTS counters (
                name VARCHAR(30) NOT NULL,
                year SMALLINT UNSIGNED NOT NULL,
                value INT UNSIGNED NOT NULL,
                PRIMARY KEY (name, year)
            ) $t",

            "CREATE TABLE IF NOT EXISTS offers (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_id CHAR(24) NOT NULL,
                number VARCHAR(30) NOT NULL,
                parent_id INT UNSIGNED NULL,
                customer_id INT UNSIGNED NOT NULL,
                product_area_id INT UNSIGNED NULL,
                area_type ENUM('digital','physical') NOT NULL DEFAULT 'digital',
                title VARCHAR(190) NOT NULL,
                template_id INT UNSIGNED NULL,
                intro TEXT NULL,
                footer TEXT NULL,
                currency CHAR(3) NOT NULL,
                status ENUM('open','accepted','declined','expired','superseded') NOT NULL DEFAULT 'open',
                valid_until DATE NULL,
                customer_snapshot LONGTEXT NOT NULL,
                tax_template_id INT UNSIGNED NULL,
                tax_exempt TINYINT(1) NOT NULL DEFAULT 0,
                tax_exempt_reason VARCHAR(255) NULL,
                discount_type ENUM('none','percent','amount') NOT NULL DEFAULT 'none',
                discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
                shipping_method VARCHAR(120) NULL,
                shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                shipping_tax_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
                shipping_tax_label VARCHAR(60) NULL,
                delivery_info VARCHAR(255) NULL,
                delivery_address LONGTEXT NULL,
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
                discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                net_total DECIMAL(12,2) NOT NULL DEFAULT 0,
                tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
                rounding DECIMAL(12,2) NOT NULL DEFAULT 0,
                total DECIMAL(12,2) NOT NULL DEFAULT 0,
                tax_breakdown LONGTEXT NULL,
                created_by INT UNSIGNED NULL,
                created_by_name VARCHAR(120) NULL,
                created_at DATETIME NOT NULL,
                sent_at DATETIME NULL,
                viewed_at DATETIME NULL,
                require_signature TINYINT(1) NOT NULL DEFAULT 0,
                signature_mode ENUM('none','open','strict') NOT NULL DEFAULT 'none',
                signer_name VARCHAR(190) NULL,
                signer_email VARCHAR(190) NULL,
                signer_verified_at DATETIME NULL,
                signer_verified_email VARCHAR(190) NULL,
                signature_file VARCHAR(80) NULL,
                responded_at DATETIME NULL,
                response_name VARCHAR(190) NULL,
                response_note TEXT NULL,
                response_ip VARCHAR(45) NULL,
                UNIQUE KEY uq_offers_public (public_id),
                UNIQUE KEY uq_offers_number (number),
                KEY idx_offers_status (status),
                KEY idx_offers_customer (customer_id),
                CONSTRAINT fk_offer_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
                CONSTRAINT fk_offer_parent FOREIGN KEY (parent_id) REFERENCES offers(id) ON DELETE SET NULL,
                CONSTRAINT fk_offer_area FOREIGN KEY (product_area_id) REFERENCES product_areas(id) ON DELETE SET NULL,
                CONSTRAINT fk_offer_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) $t",

            "CREATE TABLE IF NOT EXISTS offer_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                offer_id INT UNSIGNED NOT NULL,
                position SMALLINT UNSIGNED NOT NULL,
                title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
                unit VARCHAR(30) NULL,
                unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
                discount_type ENUM('none','percent','amount') NOT NULL DEFAULT 'none',
                discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
                tax_template_id INT UNSIGNED NULL,
                tax_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
                tax_label VARCHAR(60) NULL,
                line_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
                line_discount DECIMAL(12,2) NOT NULL DEFAULT 0,
                line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
                KEY idx_items_offer (offer_id, position),
                CONSTRAINT fk_item_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE
            ) $t",

            "CREATE TABLE IF NOT EXISTS orders (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                number VARCHAR(30) NOT NULL,
                offer_id INT UNSIGNED NOT NULL,
                customer_id INT UNSIGNED NOT NULL,
                product_area_id INT UNSIGNED NULL,
                area_type ENUM('digital','physical') NOT NULL DEFAULT 'digital',
                title VARCHAR(190) NOT NULL,
                currency CHAR(3) NOT NULL,
                status ENUM('new','in_progress','fulfilled','completed','cancelled') NOT NULL DEFAULT 'new',
                net_total DECIMAL(12,2) NOT NULL DEFAULT 0,
                tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
                total DECIMAL(12,2) NOT NULL DEFAULT 0,
                shipping_method VARCHAR(120) NULL,
                shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                shipping_status ENUM('pending','packed','shipped','delivered','returned') NULL,
                ship_company VARCHAR(190) NULL,
                ship_name VARCHAR(190) NULL,
                ship_street VARCHAR(190) NULL,
                ship_extra VARCHAR(190) NULL,
                ship_zip VARCHAR(20) NULL,
                ship_city VARCHAR(120) NULL,
                ship_country CHAR(2) NULL,
                carrier VARCHAR(80) NULL,
                tracking_number VARCHAR(120) NULL,
                tracking_url VARCHAR(500) NULL,
                shipped_at DATETIME NULL,
                delivered_at DATETIME NULL,
                delivery_email VARCHAR(190) NULL,
                download_url VARCHAR(500) NULL,
                access_info TEXT NULL,
                customer_message TEXT NULL,
                internal_note TEXT NULL,
                responsible_id INT UNSIGNED NULL,
                responsible_name VARCHAR(120) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                completed_at DATETIME NULL,
                UNIQUE KEY uq_orders_number (number),
                KEY idx_orders_responsible (responsible_id),
                UNIQUE KEY uq_orders_offer (offer_id),
                KEY idx_orders_status (status),
                KEY idx_orders_customer (customer_id),
                CONSTRAINT fk_order_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE RESTRICT,
                CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
                CONSTRAINT fk_order_area FOREIGN KEY (product_area_id) REFERENCES product_areas(id) ON DELETE SET NULL
            ) $t",

            "CREATE TABLE IF NOT EXISTS status_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entity_type ENUM('offer','order','invoice') NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                event VARCHAR(40) NOT NULL DEFAULT 'status',
                status_from VARCHAR(30) NULL,
                status_to VARCHAR(30) NULL,
                note TEXT NULL,
                customer_visible TINYINT(1) NOT NULL DEFAULT 0,
                actor_type ENUM('user','customer','system') NOT NULL DEFAULT 'system',
                actor_id INT UNSIGNED NULL,
                actor_name VARCHAR(190) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_hist_entity (entity_type, entity_id, created_at)
            ) $t",

            "CREATE TABLE IF NOT EXISTS mail_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                recipient VARCHAR(190) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                status ENUM('sent','failed') NOT NULL,
                error TEXT NULL,
                context VARCHAR(60) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_mail_created (created_at)
            ) $t",

            "CREATE TABLE IF NOT EXISTS invoices (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_id CHAR(24) NOT NULL,
                number VARCHAR(30) NOT NULL,
                order_id INT UNSIGNED NOT NULL,
                customer_id INT UNSIGNED NOT NULL,
                template_id INT UNSIGNED NULL,
                area_type ENUM('digital','physical') NOT NULL DEFAULT 'digital',
                title VARCHAR(190) NOT NULL,
                intro TEXT NULL,
                footer TEXT NULL,
                payment_info TEXT NULL,
                currency CHAR(3) NOT NULL,
                status ENUM('draft','sent','paid','cancelled') NOT NULL DEFAULT 'draft',
                kind ENUM('final','advance','credit') NOT NULL DEFAULT 'final',
                credit_of INT UNSIGNED NULL,
                subscription_id INT UNSIGNED NULL,
                amount_due DECIMAL(12,2) NOT NULL DEFAULT 0,
                deductions LONGTEXT NULL,
                qr_reference VARCHAR(40) NULL,
                issue_date DATE NOT NULL,
                due_date DATE NOT NULL,
                customer_snapshot LONGTEXT NOT NULL,
                delivery_address LONGTEXT NULL,
                tax_exempt TINYINT(1) NOT NULL DEFAULT 0,
                tax_exempt_reason VARCHAR(255) NULL,
                discount_type ENUM('none','percent','amount') NOT NULL DEFAULT 'none',
                discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
                shipping_method VARCHAR(120) NULL,
                shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                delivery_info VARCHAR(255) NULL,
                service_period VARCHAR(120) NULL,
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
                discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                net_total DECIMAL(12,2) NOT NULL DEFAULT 0,
                tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
                rounding DECIMAL(12,2) NOT NULL DEFAULT 0,
                total DECIMAL(12,2) NOT NULL DEFAULT 0,
                tax_breakdown LONGTEXT NULL,
                paid_at DATE NULL,
                payment_note VARCHAR(255) NULL,
                reminder_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                last_reminder_at DATETIME NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                sent_at DATETIME NULL,
                viewed_at DATETIME NULL,
                UNIQUE KEY uq_invoices_public (public_id),
                UNIQUE KEY uq_invoices_number (number),
                KEY idx_invoices_status (status, due_date),
                KEY idx_invoices_order (order_id),
                KEY idx_invoices_customer (customer_id),
                CONSTRAINT fk_inv_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
                CONSTRAINT fk_inv_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
            ) $t",

            "CREATE TABLE IF NOT EXISTS subscriptions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id INT UNSIGNED NOT NULL,
                customer_id INT UNSIGNED NOT NULL,
                title VARCHAR(190) NOT NULL,
                period ENUM('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
                anchor_day TINYINT UNSIGNED NOT NULL DEFAULT 1,
                next_run DATE NOT NULL,
                end_date DATE NULL,
                auto_send TINYINT(1) NOT NULL DEFAULT 0,
                payment_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
                status ENUM('active','paused','ended') NOT NULL DEFAULT 'active',
                runs INT UNSIGNED NOT NULL DEFAULT 0,
                last_run DATE NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                KEY idx_sub_due (status, next_run),
                CONSTRAINT fk_sub_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
                CONSTRAINT fk_sub_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
            ) $t",

            "CREATE TABLE IF NOT EXISTS attachments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entity_type ENUM('offer','order','invoice') NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name CHAR(40) NOT NULL,
                mime VARCHAR(120) NOT NULL,
                size INT UNSIGNED NOT NULL,
                customer_visible TINYINT(1) NOT NULL DEFAULT 0,
                by_customer TINYINT(1) NOT NULL DEFAULT 0,
                uploaded_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_att_name (stored_name),
                KEY idx_att_entity (entity_type, entity_id)
            ) $t",

            "CREATE TABLE IF NOT EXISTS signature_codes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                offer_id INT UNSIGNED NOT NULL,
                email VARCHAR(190) NOT NULL,
                code_hash CHAR(64) NOT NULL,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                request_ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_sigcode_offer (offer_id, used_at),
                CONSTRAINT fk_sigcode_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE
            ) $t",

            "CREATE TABLE IF NOT EXISTS payments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT UNSIGNED NOT NULL,
                provider ENUM('mollie','stripe') NOT NULL,
                provider_ref VARCHAR(120) NULL,
                status ENUM('open','paid','failed','canceled','expired') NOT NULL DEFAULT 'open',
                amount DECIMAL(12,2) NOT NULL,
                currency CHAR(3) NOT NULL,
                method VARCHAR(40) NULL,
                note VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                paid_at DATETIME NULL,
                UNIQUE KEY uq_pay_ref (provider, provider_ref),
                KEY idx_pay_invoice (invoice_id),
                CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT
            ) $t",

            "CREATE TABLE IF NOT EXISTS passkeys (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                credential_id VARCHAR(400) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                public_key TEXT NOT NULL,
                alg SMALLINT NOT NULL,
                sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                name VARCHAR(80) NOT NULL,
                created_at DATETIME NOT NULL,
                last_used_at DATETIME NULL,
                UNIQUE KEY uq_passkey_cred (credential_id),
                KEY idx_passkey_user (user_id),
                CONSTRAINT fk_passkey_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS invoice_payments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT UNSIGNED NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                paid_on DATE NOT NULL,
                method VARCHAR(20) NOT NULL DEFAULT 'bank',
                reference VARCHAR(190) NULL,
                note VARCHAR(255) NULL,
                provider_payment_id INT UNSIGNED NULL,
                created_by INT UNSIGNED NULL,
                created_by_name VARCHAR(120) NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_ipay_provider (provider_payment_id),
                KEY idx_ipay_invoice (invoice_id),
                KEY idx_ipay_date (paid_on),
                CONSTRAINT fk_ipay_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT
            ) $t",
            "CREATE TABLE IF NOT EXISTS invoice_adjustments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT UNSIGNED NOT NULL,
                kind ENUM('discount','credit') NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                adj_date DATE NOT NULL,
                credit_invoice_id INT UNSIGNED NULL,
                note VARCHAR(255) NULL,
                created_by_name VARCHAR(120) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_iadj_invoice (invoice_id),
                KEY idx_iadj_date (adj_date),
                CONSTRAINT fk_iadj_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT
            ) $t",
            "CREATE TABLE IF NOT EXISTS invoice_installments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT UNSIGNED NOT NULL,
                position SMALLINT UNSIGNED NOT NULL,
                label VARCHAR(80) NULL,
                due_date DATE NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                KEY idx_inst_invoice (invoice_id, position),
                CONSTRAINT fk_inst_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
            ) $t",
            "CREATE TABLE IF NOT EXISTS inquiries (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                company VARCHAR(190) NULL,
                email VARCHAR(190) NOT NULL,
                phone VARCHAR(50) NULL,
                subject VARCHAR(190) NULL,
                message TEXT NOT NULL,
                customer_id INT UNSIGNED NULL,
                offer_id INT UNSIGNED NULL,
                status ENUM('new','handled','spam') NOT NULL DEFAULT 'new',
                consent_at DATETIME NOT NULL,
                ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL,
                handled_at DATETIME NULL,
                KEY idx_inq_status (status, created_at),
                KEY idx_inq_email (email)
            ) $t",
            "CREATE TABLE IF NOT EXISTS audit_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                user_label VARCHAR(190) NULL,
                action VARCHAR(40) NOT NULL,
                entity VARCHAR(40) NULL,
                entity_id INT UNSIGNED NULL,
                detail VARCHAR(500) NULL,
                ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_audit_time (created_at),
                KEY idx_audit_action (action, created_at)
            ) $t",

            "CREATE TABLE IF NOT EXISTS api_tokens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                token_prefix CHAR(8) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                scopes VARCHAR(100) NOT NULL DEFAULT '',
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                expires_at DATETIME NULL,
                last_used_at DATETIME NULL,
                revoked_at DATETIME NULL,
                UNIQUE KEY uq_api_token (token_hash)
            ) $t",

            "CREATE TABLE IF NOT EXISTS webhooks (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                url VARCHAR(500) NOT NULL,
                secret TEXT NOT NULL,
                events TEXT NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            ) $t",

            "CREATE TABLE IF NOT EXISTS webhook_deliveries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                webhook_id INT UNSIGNED NOT NULL,
                event VARCHAR(60) NOT NULL,
                payload LONGTEXT NOT NULL,
                status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                next_attempt DATETIME NULL,
                last_http SMALLINT NULL,
                last_error VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                sent_at DATETIME NULL,
                KEY idx_wh_due (status, next_attempt),
                CONSTRAINT fk_whd_hook FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
            ) $t",

            "CREATE TABLE IF NOT EXISTS invoice_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT UNSIGNED NOT NULL,
                position SMALLINT UNSIGNED NOT NULL,
                title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
                unit VARCHAR(30) NULL,
                unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
                discount_type ENUM('none','percent','amount') NOT NULL DEFAULT 'none',
                discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
                tax_template_id INT UNSIGNED NULL,
                tax_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
                tax_label VARCHAR(60) NULL,
                line_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
                line_discount DECIMAL(12,2) NOT NULL DEFAULT 0,
                line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
                KEY idx_inv_items (invoice_id, position),
                CONSTRAINT fk_inv_item FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
            ) $t",

            "CREATE TABLE IF NOT EXISTS doc_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                doc_type ENUM('all','offer','invoice') NOT NULL DEFAULT 'all',
                is_default_offer TINYINT(1) NOT NULL DEFAULT 0,
                is_default_invoice TINYINT(1) NOT NULL DEFAULT 0,
                config LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL
            ) $t",

            "CREATE TABLE IF NOT EXISTS portal_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                customer_id INT UNSIGNED NOT NULL,
                entity_type ENUM('general','offer','order','invoice') NOT NULL DEFAULT 'general',
                entity_id INT UNSIGNED NULL,
                direction ENUM('in','out') NOT NULL,
                user_id INT UNSIGNED NULL,
                author VARCHAR(190) NOT NULL,
                body TEXT NOT NULL,
                read_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                KEY idx_pm_customer (customer_id, created_at),
                KEY idx_pm_unread (direction, read_at),
                KEY idx_pm_entity (entity_type, entity_id)
            ) $t",
        ];
    }

    /** Aktualisiert bestehende Installationen (idempotent). */
    public static function migrate(PDO $pdo): void
    {
        foreach (self::statements() as $sql) {
            $pdo->exec($sql);
        }
        $add = static function (string $table, string $column, string $def) use ($pdo): void {
            if (!$pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetch()) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $def");
            }
        };
        $hadKind = (bool) $pdo->query("SHOW COLUMNS FROM invoices LIKE 'kind'")->fetch();
        $add('invoices', 'kind', "ENUM('final','advance') NOT NULL DEFAULT 'final'");
        $add('invoices', 'subscription_id', 'INT UNSIGNED NULL');
        $add('invoices', 'amount_due', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $add('invoices', 'deductions', 'LONGTEXT NULL');
        $add('invoices', 'qr_reference', 'VARCHAR(40) NULL');
        if (!$hadKind) {
            $pdo->exec('UPDATE invoices SET amount_due = total');
        }
        $add('users', 'totp_secret', 'VARCHAR(255) NULL');
        $add('users', 'totp_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
        $add('users', 'totp_last_step', 'BIGINT NULL');
        $add('users', 'recovery_codes', 'TEXT NULL');
        $add('users', 'sso_issuer', 'VARCHAR(255) NULL');
        $add('users', 'sso_sub', 'VARCHAR(190) NULL');
        $add('users', 'changelog_seen', 'VARCHAR(20) NULL');
        $add('offers', 'require_signature', 'TINYINT(1) NOT NULL DEFAULT 0');
        $add('offers', 'signature_file', 'VARCHAR(80) NULL');
        $add('api_tokens', 'scopes', "VARCHAR(100) NOT NULL DEFAULT ''");
        $hadIssuer = (bool) $pdo->query("SHOW COLUMNS FROM offers LIKE 'created_by_name'")->fetch();
        $add('offers', 'created_by_name', 'VARCHAR(120) NULL');
        $hadResp = (bool) $pdo->query("SHOW COLUMNS FROM orders LIKE 'responsible_id'")->fetch();
        $add('orders', 'responsible_id', 'INT UNSIGNED NULL');
        $add('orders', 'responsible_name', 'VARCHAR(120) NULL');
        if (!$hadResp) {
            $pdo->exec('ALTER TABLE orders ADD KEY idx_orders_responsible (responsible_id)');
        }
        if (!$hadIssuer) {
            $pdo->exec('UPDATE offers o JOIN users u ON u.id = o.created_by SET o.created_by_name = u.name');
        }
        if (!$hadResp) {   // Bestehende Aufträge: Verantwortlich = Aussteller der Offerte (sofern Konto aktiv)
            $pdo->exec('UPDATE orders r JOIN offers o ON o.id = r.offer_id JOIN users u ON u.id = o.created_by AND u.active = 1 SET r.responsible_id = u.id, r.responsible_name = u.name');
        }
        $add('invoices', 'service_period', 'VARCHAR(120) NULL');
        // v9: Nur Schweiz – Steuerbefreiung auf Kundenebene entfällt, Steuervorlagen nur CH (MWST).
        $pdo->exec('UPDATE customers SET tax_exempt = 0, tax_exempt_reason = NULL WHERE tax_exempt = 1');
        $pdo->exec("UPDATE settings SET `value` = 'CH' WHERE `key` = 'company_country'");
        $pdo->exec("UPDATE settings SET `value` = 'ch' WHERE `key` = 'number_style'");
        $pdo->exec("DELETE FROM tax_templates WHERE country IS NOT NULL AND country <> 'CH'");
        $pdo->exec("UPDATE tax_templates SET country = 'CH', name = 'Ohne MWST (ausgenommen / nicht steuerbar)', label = 'Ohne MWST' WHERE country IS NULL AND rate = 0 AND name = 'Steuerfrei / nicht steuerbar'");
        $pdo->exec("UPDATE tax_templates SET country = 'CH' WHERE country IS NULL");
        // v9: Zahlungen als Journal – bestehende bezahlte Rechnungen erhalten eine Zahlung über den vollen Betrag.
        $pdo->exec("INSERT INTO invoice_payments (invoice_id, amount, paid_on, method, reference, note, provider_payment_id, created_by_name, created_at)
            SELECT i.id, i.amount_due, COALESCE(i.paid_at, DATE(i.updated_at), DATE(i.created_at)), IF(MIN(p.id) IS NULL, 'bank', 'online'), MIN(p.provider_ref), i.payment_note, MIN(p.id), NULL, COALESCE(i.updated_at, i.created_at)
            FROM invoices i LEFT JOIN payments p ON p.invoice_id = i.id AND p.status = 'paid'
            WHERE i.status = 'paid' AND i.amount_due > 0 AND NOT EXISTS (SELECT 1 FROM invoice_payments x WHERE x.invoice_id = i.id)
            GROUP BY i.id");
        // v10: Steuerarten, Gültigkeit der Sätze, Gutschriften
        $add('tax_templates', 'tax_class', "VARCHAR(16) NOT NULL DEFAULT 'taxable'");
        $add('tax_templates', 'valid_from', 'DATE NULL');
        $add('tax_templates', 'valid_to', 'DATE NULL');
        $kc = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'kind'")->fetch(PDO::FETCH_ASSOC);
        if ($kc && !str_contains((string) $kc['Type'], "'credit'")) {
            $pdo->exec("ALTER TABLE invoices MODIFY kind ENUM('final','advance','credit') NOT NULL DEFAULT 'final'");
        }
        $add('invoices', 'credit_of', 'INT UNSIGNED NULL');
        $pdo->exec("UPDATE tax_templates SET tax_class = 'none' WHERE rate = 0 AND label = 'Ohne MWST' AND tax_class = 'taxable'");
        $pdo->exec("UPDATE tax_templates SET valid_to = '2023-12-31', active = 0 WHERE rate IN (7.7, 2.5, 3.7) AND valid_to IS NULL");
        $pdo->exec("UPDATE tax_templates SET valid_from = '2024-01-01' WHERE tax_class = 'taxable' AND valid_from IS NULL AND valid_to IS NULL AND rate IN (8.1, 2.6, 3.8)");
        self::ensureTaxClasses($pdo);
        $add('customers', 'restricted_at', 'DATETIME NULL');
        $add('customers', 'anonymized_at', 'DATETIME NULL');
        $hadMode = (bool) $pdo->query("SHOW COLUMNS FROM offers LIKE 'signature_mode'")->fetch();
        $add('offers', 'signature_mode', "ENUM('none','open','strict') NOT NULL DEFAULT 'none'");
        $add('offers', 'signer_name', 'VARCHAR(190) NULL');
        $add('offers', 'signer_email', 'VARCHAR(190) NULL');
        $add('offers', 'signer_verified_at', 'DATETIME NULL');
        $add('offers', 'signer_verified_email', 'VARCHAR(190) NULL');
        if (!$hadMode) {
            $pdo->exec("UPDATE offers SET signature_mode = 'open' WHERE require_signature = 1");
        }
        $col = $pdo->query("SHOW COLUMNS FROM status_history LIKE 'entity_type'")->fetch(PDO::FETCH_ASSOC);
        if ($col && !str_contains((string) $col['Type'], "'invoice'")) {
            $pdo->exec("ALTER TABLE status_history MODIFY entity_type ENUM('offer','order','invoice') NOT NULL");
        }
        // v13: Dokumentvorlagen, Portal-Nachrichten/-Uploads
        $add('offers', 'template_id', 'INT UNSIGNED NULL');
        $add('invoices', 'template_id', 'INT UNSIGNED NULL');
        $add('attachments', 'by_customer', 'TINYINT(1) NOT NULL DEFAULT 0');
        foreach (['offer_items', 'invoice_items', 'item_templates'] as $imgTable) {
            $add($imgTable, 'image', 'CHAR(32) NULL');
        }
        DocTemplate::seed($pdo);
        // Das Lizenzsystem wurde in 1.8.0 entfernt: übrig gebliebene Einstellungen löschen.
        $pdo->exec("DELETE FROM settings WHERE `key` LIKE 'license\\_%'");
        $st = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        $st->execute(['schema_version', (string) self::VERSION]);
    }

    /** Grunddaten: Produktbereiche, Steuervorlagen (CH/LI/DE/AT), Versandarten. */
    /** Legt je Steuerart eine Vorlage an, falls noch keine existiert (idempotent). */
    public static function ensureTaxClasses(PDO $pdo): void
    {
        $sort = 20;
        foreach (TaxService::CLASSES as $key => $c) {
            $sort += 5;
            if ($c['taxed'] || $key === 'none') {
                continue;   // Sätze/„Ohne MWST“ stehen bereits im Grundbestand
            }
            $has = $pdo->prepare('SELECT COUNT(*) FROM tax_templates WHERE tax_class = ?');
            $has->execute([$key]);
            if (!(int) $has->fetchColumn()) {
                $pdo->prepare("INSERT INTO tax_templates (country, name, label, rate, tax_class, is_default, sort) VALUES ('CH', ?, ?, 0, ?, 0, ?)")
                    ->execute([$c['name'], $c['label'], $key, $sort]);
            }
        }
    }

    public static function seed(PDO $pdo): void
    {
        $now = date('Y-m-d H:i:s');

        if (!(int) $pdo->query('SELECT COUNT(*) FROM product_areas')->fetchColumn()) {
            $st = $pdo->prepare('INSERT INTO product_areas (name, type, description, icon, sort, created_at) VALUES (?,?,?,?,?,?)');
            $st->execute(['Digital', 'digital', 'Software, Lizenzen, Downloads, Online-Dienstleistungen und Zugänge.', 'bx-cloud-download', 1, $now]);
            $st->execute(['Physisch', 'physical', 'Waren und Produkte mit Versand oder Abholung.', 'bx-package', 2, $now]);
        }

        if (!(int) $pdo->query('SELECT COUNT(*) FROM tax_templates')->fetchColumn()) {
            $rows = [
                // country, name, label, rate, default, sort
                ['CH', 'Normalsatz', 'MWST', 8.1, 1, 10],
                ['CH', 'Reduzierter Satz (Lebensmittel, Medikamente, Bücher …)', 'MWST', 2.6, 0, 11],
                ['CH', 'Sondersatz Beherbergung', 'MWST', 3.8, 0, 12],
                ['CH', 'Ohne MWST (ausgenommen / nicht steuerbar)', 'Ohne MWST', 0, 0, 90],
            ];
            $st = $pdo->prepare('INSERT INTO tax_templates (country, name, label, rate, is_default, sort) VALUES (?,?,?,?,?,?)');
            foreach ($rows as $r) {
                $st->execute($r);
            }
            $pdo->exec("UPDATE tax_templates SET tax_class = 'none' WHERE rate = 0 AND label = 'Ohne MWST'");
            $pdo->exec("UPDATE tax_templates SET valid_from = '2024-01-01' WHERE rate > 0");   // Sätze gelten seit 1.1.2024
        }
        self::ensureTaxClasses($pdo);
        DocTemplate::seed($pdo);

        if (!(int) $pdo->query('SELECT COUNT(*) FROM shipping_methods')->fetchColumn()) {
            $st = $pdo->prepare('INSERT INTO shipping_methods (name, description, cost, sort) VALUES (?,?,?,?)');
            $st->execute(['Paket Economy', 'Zustellung in 2–3 Werktagen', 9.00, 1]);
            $st->execute(['Paket Priority', 'Zustellung am nächsten Werktag', 11.00, 2]);
            $st->execute(['Kurier', 'Zustellung am selben Tag', 35.00, 3]);
            $st->execute(['Abholung', 'Abholung nach Vereinbarung', 0.00, 4]);
        }

        $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
            ->execute([(string) self::VERSION]);
    }
}
