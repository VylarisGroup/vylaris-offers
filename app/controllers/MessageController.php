<?php
declare(strict_types=1);

/** Posteingang des Kundenportals (Team). */
final class MessageController
{
    public function index(): void
    {
        Auth::requireUser();
        view('admin/messages/index', ['rows' => PortalMessage::inbox()]);
    }

    public function show(string $id): void
    {
        Auth::requireUser();
        $c = DB::one('SELECT * FROM customers WHERE id = ?', [(int) $id]);
        if (!$c) {
            throw new HttpException(404, 'Kunde nicht gefunden.');
        }
        $thread = PortalMessage::thread((int) $c['id']);
        PortalMessage::markRead((int) $c['id'], 'in');
        view('admin/messages/show', compact('c', 'thread'));
    }

    public function reply(string $id): void
    {
        $user = Auth::requireUser();
        $c = DB::one('SELECT * FROM customers WHERE id = ?', [(int) $id]);
        if (!$c) {
            throw new HttpException(404, 'Kunde nicht gefunden.');
        }
        try {
            [$type, $eid] = PortalMessage::parseRef((int) $c['id'], input_str('ref', 40));
            PortalMessage::add((int) $c['id'], 'out', (string) input('body', ''), (string) $user['name'], (int) $user['id'], $type, $eid);
            PortalMessage::markRead((int) $c['id'], 'in');
            $sent = !empty($c['portal_enabled']) && empty($c['anonymized_at']) ? Notifier::portalMessageToCustomer($c) : false;
            flash('success', $sent ? 'Antwort gesendet; der Kunde wurde per E-Mail benachrichtigt.' : 'Antwort gespeichert (der Kunde konnte nicht per E-Mail benachrichtigt werden – er sieht sie im Portal).');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/messages/' . (int) $c['id']);
    }
}
