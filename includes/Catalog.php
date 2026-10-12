<?php
namespace Synergetic\Telegram;
/** Reviewed operations only; no arbitrary MTProto RPC dispatch or local paths. */
final class Catalog {
    public static function all(): array {
        $peer = ['type' => 'string', 'minLength' => 1, 'maxLength' => 200, 'description' => 'Telegram username (@name) or numeric dialog ID as a string.'];
        $text = ['type' => 'string', 'minLength' => 1, 'maxLength' => 4096];
        $id = ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647];
        $limit = ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25];
        $offset = ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647, 'default' => 0];
        $ids = ['type' => 'array', 'minItems' => 1, 'maxItems' => 100, 'items' => $id];
        $boolean = ['type' => 'boolean'];
        $defs = [
            'get_me' => ['getSelf', 'Read the connected personal Telegram identity.', [], [], []],
            'get_dialogs' => ['messages.getDialogs', 'List dialogs with pagination. Use offset_peer/date/id from the last result for the next page.', ['limit' => $limit, 'offset_id' => $offset, 'offset_date' => $offset, 'offset_peer' => $peer], [], ['offset_peer' => ['_' => 'inputPeerEmpty'], 'hash' => 0]],
            'get_chat' => ['getInfo', 'Read information about a chat, channel or user.', ['peer' => $peer], ['peer'], []],
            'get_history' => ['messages.getHistory', 'Read a bounded page of chat history without marking it read.', ['peer' => $peer, 'limit' => $limit, 'offset_id' => $offset], ['peer'], ['offset_date' => 0, 'add_offset' => 0, 'max_id' => 0, 'min_id' => 0, 'hash' => 0]],
            'search_messages' => ['messages.search', 'Search messages in one chat.', ['peer' => $peer, 'q' => $text, 'limit' => $limit, 'offset_id' => $offset], ['peer', 'q'], ['filter' => ['_' => 'inputMessagesFilterEmpty'], 'min_date' => 0, 'max_date' => 0, 'add_offset' => 0, 'max_id' => 0, 'min_id' => 0, 'hash' => 0]],
            'search_global' => ['messages.searchGlobal', 'Search across dialogs; supply next-page offset_rate, peer and id from the response.', ['q' => $text, 'limit' => $limit, 'offset_rate' => $offset, 'offset_id' => $offset, 'offset_peer' => $peer], ['q'], ['filter' => ['_' => 'inputMessagesFilterEmpty'], 'min_date' => 0, 'max_date' => 0, 'offset_peer' => ['_' => 'inputPeerEmpty']]],
            'get_messages' => ['messages.getMessages', 'Read messages by ID from private/basic-group dialogs. For channels use get_channel_messages.', ['id' => $ids], ['id'], []],
            'get_channel_messages' => ['channels.getMessages', 'Read messages by ID in a channel/supergroup.', ['channel' => $peer, 'id' => $ids], ['channel', 'id'], []],
            'get_contacts' => ['contacts.getContacts', 'Read personal Telegram contacts.', [], [], ['hash' => 0]],
            'search_contacts' => ['contacts.search', 'Search Telegram users by name or username.', ['q' => $text, 'limit' => $limit], ['q'], []],
            'resolve_username' => ['getInfo', 'Resolve a Telegram username.', ['username' => $peer], ['username'], []],
            'get_full_user' => ['getFullInfo', 'Read full user details.', ['id' => $peer], ['id'], []],
            'get_full_channel' => ['getFullInfo', 'Read full channel/supergroup details.', ['channel' => $peer], ['channel'], []],
            'get_full_group' => ['getFullInfo', 'Read a basic-group chat by numeric ID.', ['chat_id' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 999999999999]], ['chat_id'], []],
            'get_participants' => ['channels.getParticipants', 'Read a bounded page of channel/supergroup members.', ['channel' => $peer, 'offset' => $offset, 'limit' => $limit], ['channel'], ['filter' => ['_' => 'channelParticipantsRecent'], 'hash' => 0]],
            'get_participant' => ['channels.getParticipant', 'Read one member’s channel role.', ['channel' => $peer, 'participant' => $peer], ['channel', 'participant'], []],
            'get_blocked' => ['contacts.getBlocked', 'Read a bounded page of blocked peers.', ['offset' => $offset, 'limit' => $limit], [], []],
            'get_dialog_filters' => ['messages.getDialogFilters', 'Read chat-folder definitions.', [], [], []],
            'get_pinned_dialogs' => ['messages.getPinnedDialogs', 'Read pinned dialogs in folder 0 (main) or 1 (archive).', ['folder_id' => ['type' => 'integer', 'enum' => [0, 1], 'default' => 0]], [], []],
            'get_drafts' => ['messages.getAllDrafts', 'Read saved message drafts.', [], [], []],
            'get_scheduled_messages' => ['messages.getScheduledHistory', 'Read scheduled messages for a chat.', ['peer' => $peer], ['peer'], ['hash' => 0]],
            'get_profile_photos' => ['photos.getUserPhotos', 'Read metadata for profile photos (binary download is deferred).', ['user_id' => $peer, 'offset' => $offset, 'limit' => $limit], ['user_id'], ['max_id' => 0]],
            'get_notify_settings' => ['account.getNotifySettings', 'Read notification settings for a chat.', ['peer' => $peer], ['peer'], []],
            'get_privacy' => ['account.getPrivacy', 'Read a supported personal-account privacy rule.', ['key' => ['type' => 'string', 'enum' => ['status', 'phone', 'profile_photo', 'forwards', 'calls', 'invites']]], ['key'], []],
            'get_active_sessions' => ['account.getAuthorizations', 'Read active Telegram device/session metadata.', [], [], []],
            'get_saved_gifs' => ['messages.getSavedGifs', 'Read saved GIF metadata.', [], [], ['hash' => 0]],
            'get_saved_stickers' => ['messages.getFavedStickers', 'Read favourite sticker metadata.', [], [], ['hash' => 0]],
            'send_message' => ['messages.sendMessage', 'Send a plain-text message; this contacts the recipient.', ['peer' => $peer, 'message' => $text, 'reply_to_id' => $id, 'silent' => $boolean], ['peer', 'message'], [], true],
            'edit_message' => ['messages.editMessage', 'Edit an existing message that your account can edit.', ['peer' => $peer, 'id' => $id, 'message' => $text], ['peer', 'id', 'message'], [], true],
            'delete_messages' => ['messages.deleteMessages', 'Delete messages in private/basic-group dialogs. revoke=true deletes for recipients where permitted.', ['id' => $ids, 'revoke' => $boolean], ['id', 'revoke'], [], true],
            'delete_channel_messages' => ['channels.deleteMessages', 'Delete messages in a channel/supergroup where permitted.', ['channel' => $peer, 'id' => $ids], ['channel', 'id'], [], true],
            'forward_messages' => ['messages.forwardMessages', 'Forward messages between chats.', ['from_peer' => $peer, 'to_peer' => $peer, 'id' => $ids, 'silent' => $boolean], ['from_peer', 'to_peer', 'id'], [], true],
            'mark_read' => ['messages.readHistory', 'Mark messages read in a private/basic-group chat.', ['peer' => $peer, 'max_id' => $id], ['peer', 'max_id'], [], true],
            'mark_channel_read' => ['channels.readHistory', 'Mark messages read in a channel/supergroup.', ['channel' => $peer, 'max_id' => $id], ['channel', 'max_id'], [], true],
            'save_draft' => ['messages.saveDraft', 'Save or clear a message draft without sending it.', ['peer' => $peer, 'message' => ['type' => 'string', 'maxLength' => 4096]], ['peer', 'message'], [], true],
            'pin_message' => ['messages.updatePinnedMessage', 'Pin a chat message.', ['peer' => $peer, 'id' => $id, 'silent' => $boolean], ['peer', 'id'], [], true],
            'unpin_message' => ['messages.updatePinnedMessage', 'Unpin a chat message.', ['peer' => $peer, 'id' => $id], ['peer', 'id'], ['unpin' => true], true],
            'block_peer' => ['contacts.block', 'Block a peer.', ['id' => $peer], ['id'], [], true],
            'unblock_peer' => ['contacts.unblock', 'Unblock a peer.', ['id' => $peer], ['id'], [], true],
            'join_channel' => ['channels.joinChannel', 'Join a public channel or supergroup.', ['channel' => $peer], ['channel'], [], true],
            'leave_channel' => ['channels.leaveChannel', 'Leave a channel or supergroup.', ['channel' => $peer], ['channel'], [], true],
            'update_profile' => ['account.updateProfile', 'Update your personal profile. Omitted fields are preserved.', ['first_name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64], 'last_name' => ['type' => 'string', 'maxLength' => 64], 'about' => ['type' => 'string', 'maxLength' => 70]], [], [], true],
            'update_username' => ['account.updateUsername', 'Change or clear your account username.', ['username' => ['type' => 'string', 'maxLength' => 32, 'pattern' => '^[A-Za-z0-9_]*$']], ['username'], [], true],
            'archive_chat' => ['folders.editPeerFolders', 'Archive or unarchive a dialog.', ['peer' => $peer, 'archived' => $boolean], ['peer', 'archived'], [], true],
        ];
        $result = [];
        foreach ($defs as $name => $d) {
            if (array_key_exists('hash', $d[4])) { $d[4]['hash'] = []; }
            $write = $d[5] ?? false;
            $properties = $d[2]; $required = $d[3];
            if ($write) { $properties['confirm'] = ['type' => 'boolean', 'const' => true, 'description' => 'Explicitly confirm this mutation.']; $required[] = 'confirm'; }
            $result[$name] = ['name' => $name, 'method' => $d[0], 'description' => $d[1], 'defaults' => $d[4], 'write' => $write,
                'schema' => ['type' => 'object', 'properties' => $properties ?: (object)[], 'required' => $required, 'additionalProperties' => false]];
        }
        return $result;
    }
    public static function arguments(string $tool, array $args): array {
        $def = self::all()[$tool] ?? null;
        if (!$def) { throw new Fault('Unknown Telegram tool.', 404); }
        $properties = (array)$def['schema']['properties'];
        if (array_diff(array_keys($args), array_keys($properties))) { throw new Fault('Unexpected Telegram tool argument.'); }
        foreach ($def['schema']['required'] as $key) { if (!array_key_exists($key, $args)) { throw new Fault('Missing argument: ' . $key); } }
        foreach ($properties as $key => $schema) {
            if (!array_key_exists($key, $args)) { if (array_key_exists('default', $schema)) { $args[$key] = $schema['default']; } continue; }
            self::validate($key, $args[$key], $schema);
        }
        unset($args['confirm']);
        if ($tool === 'update_profile' && !$args) { throw new Fault('Supply at least one profile field.'); }
        return array_merge($def['defaults'], $args);
    }
    private static function validate(string $key, mixed $value, array $s): void {
        $valid = match ($s['type']) { 'string' => is_string($value), 'integer' => is_int($value), 'boolean' => is_bool($value), 'array' => is_array($value) && array_is_list($value), default => false };
        if (!$valid || (isset($s['const']) && $value !== $s['const']) || (isset($s['enum']) && !in_array($value, $s['enum'], true))) { throw new Fault('Invalid argument: ' . $key); }
        if (is_int($value) && ($value < ($s['minimum'] ?? PHP_INT_MIN) || $value > ($s['maximum'] ?? PHP_INT_MAX))) { throw new Fault('Argument out of range: ' . $key); }
        if (is_string($value)) {
            $length = mb_strlen($value, 'UTF-8');
            if (!mb_check_encoding($value, 'UTF-8') || $length < ($s['minLength'] ?? 0) || $length > ($s['maxLength'] ?? 4096) || (isset($s['pattern']) && !preg_match('/' . $s['pattern'] . '/D', $value))) { throw new Fault('Invalid text: ' . $key); }
        }
        if (is_array($value)) {
            if (count($value) < ($s['minItems'] ?? 0) || count($value) > ($s['maxItems'] ?? 100)) { throw new Fault('Too many or too few items: ' . $key); }
            foreach ($value as $item) { self::validate($key, $item, $s['items']); }
        }
    }
}
