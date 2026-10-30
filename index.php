<?php
/**
 * Front-Controller – alle Anfragen ausser statischen Dateien landen hier (siehe .htaccess).
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

// Aktualisierung bestehender Installationen (z. B. neue Tabellen nach einem Update) – läuft nur einmal.
try {
    if ((int) Settings::get('schema_version', 1) < Schema::VERSION) {
        Schema::migrate(DB::pdo());
        Settings::set([]);
    }
} catch (Throwable $e) {
    ErrorHandler::log($e);
}

$r = new Router();

/* ------------------------------------------------------------ Öffentlich */
$r->get('/', [SiteController::class, 'home']);
$r->get('/item-img/{name}', [ItemImageController::class, 'show']);
$r->get('/site/img/{name}', [SiteController::class, 'image']);
$r->get('/o/{id}', [PublicOfferController::class, 'show']);
$r->post('/o/{id}/accept', [PublicOfferController::class, 'accept']);
$r->post('/o/{id}/code', [PublicOfferController::class, 'sendCode']);
$r->post('/o/{id}/decline', [PublicOfferController::class, 'decline']);
$r->post('/o/{id}/request-new', [PublicOfferController::class, 'requestNew']);
$r->get('/r/{id}', [PublicInvoiceController::class, 'show']);
$r->get('/r/{id}/pdf', [PublicInvoiceController::class, 'pdf']);
$r->get('/manifest.webmanifest', [PwaController::class, 'adminManifest']);
$r->get('/portal.webmanifest', [PwaController::class, 'portalManifest']);
$r->get('/sw.js', [PwaController::class, 'serviceWorker']);
$r->get('/offline', [PwaController::class, 'offline']);
$r->get('/pwa/icon/{file}', [PwaController::class, 'icon']);
$r->get('/brand/logo', [BrandController::class, 'logo']);
$r->get('/brand/favicon', [BrandController::class, 'favicon']);
$r->get('/r/{id}/receipt', [PublicInvoiceController::class, 'receipt']);
$r->get('/r/{id}/receipt/{pid}', [PublicInvoiceController::class, 'receipt']);
$r->get('/o/{id}/pdf', [PublicOfferController::class, 'pdf']);
$r->get('/o/{id}/files/{fid}', [PublicOfferController::class, 'file']);
$r->get('/r/{id}/files/{fid}', [PublicInvoiceController::class, 'file']);
$r->get('/portal/orders/{id}/files/{fid}', [PortalController::class, 'file']);

/* ------------------------------------------------------------ Kundenportal */
$r->post('/r/{id}/pay/{provider}', [PayController::class, 'start']);
$r->get('/r/{id}/return/{pid}', [PayController::class, 'back']);
$r->post('/webhooks/mollie', [PayController::class, 'mollieHook']);
$r->post('/webhooks/stripe', [PayController::class, 'stripeHook']);
$r->post('/admin/invoices/{id}/payments/{pid}/sync', [PayController::class, 'adminSync']);
$r->get('/datenschutz', [PrivacyController::class, 'show']);
$r->post('/anfrage', [InquiryController::class, 'submit']);
$r->get('/health', [HealthController::class, 'ping']);
$r->get('/admin/doc-templates', [DocTemplateController::class, 'index']);
$r->get('/admin/doc-templates/new', [DocTemplateController::class, 'create']);
$r->get('/admin/doc-templates/preview', [DocTemplateController::class, 'preview']);
$r->post('/admin/doc-templates', [DocTemplateController::class, 'store']);
$r->get('/admin/doc-templates/{id}', [DocTemplateController::class, 'edit']);
$r->post('/admin/doc-templates/{id}', [DocTemplateController::class, 'update']);
$r->post('/admin/doc-templates/{id}/delete', [DocTemplateController::class, 'delete']);
$r->post('/admin/doc-templates/{id}/default/{type}', [DocTemplateController::class, 'makeDefault']);
$r->get('/admin/changelog', [ChangelogController::class, 'index']);
$r->post('/admin/changelog/seen', [ChangelogController::class, 'seen']);
$r->get('/admin/update', [UpdateController::class, 'index']);
$r->post('/admin/update', [UpdateController::class, 'save']);
$r->post('/admin/update/{action}', [UpdateController::class, 'action']);
$r->get('/admin/health', [HealthController::class, 'index']);
$r->post('/admin/health/token', [HealthController::class, 'token']);
$r->get('/admin/portal', [PortalAdminController::class, 'index']);
$r->post('/admin/portal/{id}/toggle', [PortalAdminController::class, 'toggle']);
$r->post('/admin/portal/{id}/link', [PortalAdminController::class, 'sendLink']);
$r->get('/admin/messages', [MessageController::class, 'index']);
$r->get('/admin/messages/{id}', [MessageController::class, 'show']);
$r->post('/admin/messages/{id}', [MessageController::class, 'reply']);
$r->get('/admin/inquiries', [InquiryController::class, 'index']);
$r->post('/admin/inquiries/{id}/status/{status}', [InquiryController::class, 'setStatus']);
$r->post('/admin/inquiries/{id}/delete', [InquiryController::class, 'delete']);
$r->get('/portal', [PortalController::class, 'index']);
$r->post('/portal/export', [PrivacyController::class, 'selfExport']);
$r->post('/portal/login', [PortalController::class, 'requestLink']);
$r->get('/portal/auth/{token}', [PortalController::class, 'confirm']);
$r->post('/portal/auth/{token}', [PortalController::class, 'login']);
$r->get('/portal/messages', [PortalController::class, 'messages']);
$r->post('/portal/messages', [PortalController::class, 'sendMessage']);
$r->get('/portal/documents', [PortalController::class, 'documentsPage']);
$r->post('/portal/documents', [PortalController::class, 'uploadDocument']);
$r->get('/portal/documents/{id}', [PortalController::class, 'downloadDocument']);
$r->post('/portal/documents/{id}/delete', [PortalController::class, 'deleteDocument']);
$r->get('/portal/profile', [PortalController::class, 'profile']);
$r->post('/portal/profile', [PortalController::class, 'saveProfile']);
$r->post('/portal/logout', [PortalController::class, 'logout']);
$r->get('/portal/orders/{id}', [PortalController::class, 'order']);

/* ------------------------------------------------------------ Admin: Anmeldung */
$r->get('/admin/login', [AuthController::class, 'loginForm']);
$r->post('/admin/login', [AuthController::class, 'login']);
$r->post('/admin/magic', [AuthController::class, 'requestLink']);
$r->get('/admin/auth/{token}', [AuthController::class, 'confirm']);
$r->post('/admin/auth/{token}', [AuthController::class, 'consume']);
$r->post('/admin/logout', [AuthController::class, 'logout']);
$r->get('/admin/2fa', [TwoFactorController::class, 'form']);
$r->post('/admin/2fa', [TwoFactorController::class, 'verify']);
$r->post('/admin/2fa/passkey/options', [PasskeyController::class, 'loginOptions']);
$r->post('/admin/2fa/passkey/verify', [PasskeyController::class, 'loginVerify']);
$r->post('/admin/profile/passkeys/options', [PasskeyController::class, 'registerOptions']);
$r->post('/admin/profile/passkeys/register', [PasskeyController::class, 'register']);
$r->post('/admin/profile/passkeys/{id}/delete', [PasskeyController::class, 'delete']);
$r->get('/admin/sso/login', [SsoController::class, 'start']);
$r->get('/admin/sso/callback', [SsoController::class, 'callback']);
$r->post('/admin/sso/test', [SsoController::class, 'test']);
$r->post('/admin/sso', [SsoController::class, 'save']);
$r->post('/admin/2fa/cancel', [TwoFactorController::class, 'cancel']);
$r->get('/admin/profile/2fa', [TwoFactorController::class, 'setup']);
$r->post('/admin/profile/2fa/enable', [TwoFactorController::class, 'enable']);
$r->get('/admin/profile/2fa/codes', [TwoFactorController::class, 'codes']);
$r->post('/admin/profile/2fa/disable', [TwoFactorController::class, 'disable']);
$r->post('/admin/profile/2fa/regenerate', [TwoFactorController::class, 'regenerate']);
$r->post('/admin/users/{id}/reset-2fa', [TwoFactorController::class, 'adminReset']);

/* ------------------------------------------------------------ Admin */
$r->get('/admin', [DashboardController::class, 'index']);

$r->get('/admin/customers', [CustomerController::class, 'index']);
$r->get('/admin/customers/new', [CustomerController::class, 'create']);
$r->post('/admin/customers', [CustomerController::class, 'store']);
$r->get('/admin/customers/{id}', [CustomerController::class, 'show']);
$r->get('/admin/customers/{id}/edit', [CustomerController::class, 'edit']);
$r->post('/admin/customers/{id}', [CustomerController::class, 'update']);
$r->post('/admin/customers/{id}/delete', [CustomerController::class, 'destroy']);
$r->post('/admin/customers/{id}/portal-link', [CustomerController::class, 'sendPortalLink']);
$r->post('/admin/customers/{id}/privacy-export', [CustomerController::class, 'privacyExport']);
$r->post('/admin/customers/{id}/restrict', [CustomerController::class, 'restrict']);
$r->post('/admin/customers/{id}/anonymize', [CustomerController::class, 'anonymize']);
$r->get('/admin/privacy-register', [SettingsController::class, 'privacyRegister']);
$r->post('/admin/backup', [SettingsController::class, 'backup']);

$r->get('/admin/offers', [OfferController::class, 'index']);
$r->get('/admin/offers/new', [OfferController::class, 'create']);
$r->post('/admin/offers', [OfferController::class, 'store']);
$r->get('/admin/offers/{id}', [OfferController::class, 'show']);
$r->post('/admin/offers/{id}/send', [OfferController::class, 'send']);
$r->post('/admin/orders/{id}/delete', [OrderController::class, 'destroy']);
$r->post('/admin/offers/{id}/delete', [OfferController::class, 'destroy']);

$r->get('/admin/orders', [OrderController::class, 'index']);
$r->get('/admin/orders/{id}', [OrderController::class, 'show']);
$r->post('/admin/orders/{id}/responsible', [OrderController::class, 'assign']);
$r->post('/admin/orders/{id}/status', [OrderController::class, 'status']);
$r->post('/admin/orders/{id}/fulfillment', [OrderController::class, 'fulfillment']);
$r->post('/admin/orders/{id}/invoice', [InvoiceController::class, 'store']);

$r->get('/admin/reports', [ReportController::class, 'index']);
$r->get('/admin/reports/export/{type}', [ReportController::class, 'export']);
$r->get('/admin/subscriptions', [SubscriptionController::class, 'index']);
$r->post('/admin/orders/{id}/subscription', [SubscriptionController::class, 'store']);
$r->post('/admin/subscriptions/{id}/{action}', [SubscriptionController::class, 'action']);

$r->get('/admin/invoices', [InvoiceController::class, 'index']);
$r->get('/admin/invoices/{id}', [InvoiceController::class, 'show']);
$r->get('/admin/invoices/{id}/pdf', [InvoiceController::class, 'pdf']);
$r->get('/admin/offers/{id}/pdf', [OfferController::class, 'pdf']);
$r->post('/admin/attachments/add/{type}/{id}', [AttachmentController::class, 'upload']);
$r->get('/admin/attachments/{id}', [AttachmentController::class, 'download']);
$r->post('/admin/attachments/{id}/visibility', [AttachmentController::class, 'visibility']);
$r->post('/admin/attachments/{id}/delete', [AttachmentController::class, 'destroy']);
$r->get('/admin/offers/{id}/signature', [OfferController::class, 'signature']);
$r->post('/admin/invoices/{id}/update', [InvoiceController::class, 'update']);
$r->post('/admin/invoices/{id}/send', [InvoiceController::class, 'send']);
$r->post('/admin/invoices/{id}/remind', [InvoiceController::class, 'remind']);
$r->post('/admin/invoices/{id}/paid', [InvoiceController::class, 'paid']);
$r->post('/admin/invoices/{id}/payments/{pid}/delete', [InvoiceController::class, 'removePayment']);
$r->post('/admin/invoices/{id}/plan', [InvoiceController::class, 'plan']);
$r->post('/admin/invoices/{id}/discount', [InvoiceController::class, 'discount']);
$r->post('/admin/invoices/{id}/adjustments/{aid}/delete', [InvoiceController::class, 'removeAdjustment']);
$r->post('/admin/invoices/{id}/refund', [InvoiceController::class, 'refund']);
$r->post('/admin/invoices/{id}/credit', [InvoiceController::class, 'credit']);
$r->post('/admin/invoices/{id}/cancel', [InvoiceController::class, 'cancel']);

$r->get('/admin/areas', [CatalogController::class, 'areas']);
$r->post('/admin/areas', [CatalogController::class, 'saveArea']);
$r->post('/admin/areas/{id}/delete', [CatalogController::class, 'deleteArea']);

$r->post('/admin/item-images', [ItemImageController::class, 'upload']);
$r->get('/admin/templates', [CatalogController::class, 'templates']);
$r->post('/admin/templates', [CatalogController::class, 'saveTemplate']);
$r->post('/admin/templates/quick', [CatalogController::class, 'quickTemplate']);
$r->post('/admin/templates/{id}/delete', [CatalogController::class, 'deleteTemplate']);

$r->get('/admin/taxes', [CatalogController::class, 'taxes']);
$r->post('/admin/taxes', [CatalogController::class, 'saveTax']);
$r->post('/admin/taxes/{id}/delete', [CatalogController::class, 'deleteTax']);

$r->get('/admin/shipping', [CatalogController::class, 'shipping']);
$r->post('/admin/shipping', [CatalogController::class, 'saveShipping']);
$r->post('/admin/shipping/{id}/delete', [CatalogController::class, 'deleteShipping']);

$r->get('/admin/users', [UserController::class, 'index']);
$r->post('/admin/users', [UserController::class, 'save']);
$r->post('/admin/users/{id}/delete', [UserController::class, 'destroy']);
$r->post('/admin/users/{id}/invite', [UserController::class, 'invite']);

$r->get('/admin/profile', [UserController::class, 'profile']);
$r->post('/admin/profile', [UserController::class, 'saveProfile']);

$r->get('/admin/website', [SiteController::class, 'builder']);
$r->get('/admin/website/preview', [SiteController::class, 'preview']);
$r->post('/admin/website/save', [SiteController::class, 'save']);
$r->post('/admin/website/publish', [SiteController::class, 'publish']);
$r->post('/admin/website/reset', [SiteController::class, 'reset']);
$r->post('/admin/website/discard', [SiteController::class, 'discard']);
$r->post('/admin/website/upload', [SiteController::class, 'upload']);
$r->get('/admin/settings', [SettingsController::class, 'index']);
$r->post('/admin/settings', [SettingsController::class, 'save']);
$r->post('/admin/settings/branding', [SettingsController::class, 'branding']);
$r->post('/admin/settings/test-mail', [SettingsController::class, 'testMail']);
$r->get('/admin/mail-log', [SettingsController::class, 'mailLog']);
$r->get('/admin/audit', [AuditController::class, 'index']);
$r->get('/admin/integrations', [IntegrationController::class, 'index']);
$r->get('/admin/integrations/journal', [IntegrationController::class, 'journal']);
$r->post('/admin/integrations/tokens', [IntegrationController::class, 'createToken']);
$r->post('/admin/integrations/tokens/{id}/revoke', [IntegrationController::class, 'revokeToken']);
$r->post('/admin/integrations/webhooks', [IntegrationController::class, 'saveHook']);
$r->post('/admin/integrations/webhooks/{id}', [IntegrationController::class, 'saveHook']);
$r->post('/admin/integrations/webhooks/{id}/delete', [IntegrationController::class, 'deleteHook']);
$r->post('/admin/integrations/webhooks/{id}/rotate', [IntegrationController::class, 'rotateSecret']);
$r->post('/admin/integrations/webhooks/{id}/test', [IntegrationController::class, 'testHook']);
$r->post('/admin/integrations/accounts', [IntegrationController::class, 'saveAccounts']);
$r->get('/api/v1/ping', [ApiController::class, 'ping']);
$r->get('/api/v1/{type}', [ApiController::class, 'index']);
$r->get('/api/v1/{type}/{id}', [ApiController::class, 'show']);

$r->dispatch(request_method(), Router::currentPath());
