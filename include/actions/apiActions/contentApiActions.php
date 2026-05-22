<?php
/**
 * contentApiActions.php
 * API actions for content-informed AI chat.
 * Persists conversation to DB, loads content MD for AI context,
 * and includes user tier/role in the system prompt.
 */

if (($_POST['action'] ?? '') === 'content_chat') {
    $errs = [];
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    $content_id = trim($_POST['content_id'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!$content_id) { $errs['content'] = 'Content ID required.'; }
    if (!$message) { $errs['message'] = 'Message required.'; }

    if (count($errs) <= 0) {
        // Load catalog item
        $catalog = get_content_catalog();
        $items = isset($catalog['contentCatalog']) ? $catalog['contentCatalog'] : (isset($catalog['items']) ? $catalog['items'] : $catalog);
        $content_item = null;
        foreach ($items as $ci) {
            if (($ci['id'] ?? '') === $content_id) {
                $content_item = $ci;
                break;
            }
        }

        if (!$content_item) {
            $errs['content'] = 'Content item not found.';
        }
    }

    if (count($errs) <= 0) {
        global $ai_config;

        // ── Load user context ──
        $user_state = load_user_state($uid, $sid);
        $current_stage = $user_state['currentStage'] ?? '00';
        $current_tier  = $user_state['currentTier'] ?? 'pre-spark';
        $is_admin = function_exists('has_role') && has_role('admin');
        $is_staff = function_exists('has_role') && has_role('staff');
        $user_name = ($_SESSION['first_name'] ?? '') ?: 'there';

        // ── Load content markdown (full course material) ──
        $content_md = get_content_markdown($content_item);

        // ── Load persisted conversation from DB ──
        $conv_rows = child_db_query_shard_prepared($sid,
            "SELECT role, message FROM content_chat_log WHERE user_id = ? AND content_id = ? ORDER BY created_at ASC LIMIT 40",
            [$uid, $content_id]
        );
        $conv_log = [];
        if ($conv_rows) {
            while ($row = $conv_rows->fetch(PDO::FETCH_ASSOC)) {
                $conv_log[] = ['role' => $row['role'], 'content' => $row['message']];
            }
        }

        // Add user message
        $conv_log[] = ['role' => 'user', 'content' => $message];

        // Persist user message to DB
        child_db_query_shard_prepared($sid,
            "INSERT INTO content_chat_log (user_id, content_id, role, message) VALUES (?, ?, 'user', ?)",
            [$uid, $content_id, $message]
        );

        // ── Build system prompt ──
        $system_prompt  = "You are a friendly, knowledgeable content guide for the " . CHILD_APP_NAME . " learning system.\n\n";

        // User context — tier and role weighting
        $system_prompt .= "═══ USER CONTEXT ═══\n";
        $system_prompt .= "Name: $user_name\n";
        $system_prompt .= "Stage: $current_stage\n";
        $system_prompt .= "Tier: $current_tier\n";
        if ($is_admin) {
            $system_prompt .= "Role: ADMIN — This user is a platform administrator. You may discuss implementation details, teaching methodology, and content structure openly.\n";
        } elseif ($is_staff) {
            $system_prompt .= "Role: STAFF — This user is DSWA staff. You may share deeper context about content design and framework rationale.\n";
        } else {
            $system_prompt .= "Role: Member — Tailor your answers to their tier level. ";
            if ($current_tier === 'pre-spark') {
                $system_prompt .= "They are just starting out. Keep language simple, encouraging, and action-oriented. Avoid jargon.\n";
            } elseif ($current_tier === 'rising-star') {
                $system_prompt .= "They have some experience. You can use industry terms and push for slightly deeper insights.\n";
            } elseif ($current_tier === 'pro') {
                $system_prompt .= "They are experienced. Speak at a professional level. They appreciate strategy and nuance.\n";
            } elseif ($current_tier === 'elite') {
                $system_prompt .= "They are a top-level leader. Engage at a strategic/leadership level. They think about systems, culture, and legacy.\n";
            }
        }

        // Content metadata
        $system_prompt .= "\n═══ CONTENT ITEM ═══\n";
        $system_prompt .= "Title: " . ($content_item['title'] ?? '') . "\n";
        $system_prompt .= "Type: " . ($content_item['type'] ?? '') . "\n";
        $system_prompt .= "Description: " . ($content_item['description'] ?? '') . "\n";
        $system_prompt .= "Dimensions: " . implode(', ', (array)($content_item['dimensions'] ?? [])) . "\n";
        $system_prompt .= "Target Stages: " . implode(', ', (array)($content_item['stages'] ?? [])) . "\n";
        $system_prompt .= "Duration: " . ($content_item['estimatedDuration'] ?? 'unknown') . "\n";
        $system_prompt .= "Tags: " . implode(', ', (array)($content_item['tags'] ?? [])) . "\n";

        // Full content body from markdown
        if ($content_md) {
            // Truncate to ~6000 chars to stay within token budget
            $truncated = mb_substr($content_md, 0, 6000);
            if (mb_strlen($content_md) > 6000) {
                $truncated .= "\n\n[... content continues — " . mb_strlen($content_md) . " total characters ...]";
            }
            $system_prompt .= "\n═══ FULL CONTENT (from course material) ═══\n";
            $system_prompt .= $truncated . "\n";
        }

        // Instructions
        $system_prompt .= "\n═══ INSTRUCTIONS ═══\n";
        $system_prompt .= "Help the user understand this content, answer questions about it, and connect it to their direct selling journey.\n";
        $system_prompt .= "You HAVE the full course material above — use it to give specific, accurate answers. Quote or reference specific sections when helpful.\n";
        $system_prompt .= "Be encouraging, practical, and concise. Keep responses to 2-3 sentences unless the user asks for more detail.\n";
        $system_prompt .= "If the user asks about something not in this content, you can reference the DSWA online community as a resource.\n";
        $system_prompt .= "Connect concepts back to the user's current stage ($current_stage) and practical next steps they can take.\n";
        $system_prompt .= "Never make up content that isn't in the course material. If you don't know, say so honestly.\n";

        // Trim conversation to last 20 messages for API call
        if (count($conv_log) > 20) {
            $conv_log = array_slice($conv_log, -20);
        }

        // ── Call AI ──
        $reply = '';
        try {
            $ai_response = call_ai_api($system_prompt, $conv_log, $ai_config);
            if ($ai_response && isset($ai_response['content'])) {
                $reply = $ai_response['content'];
            }
            if (!$reply) {
                $reply = 'I had trouble processing that. Could you try rephrasing?';
            }
        } catch (Exception $e) {
            error_log("[content_chat] error: " . $e->getMessage());
            $reply = 'Sorry, I\'m having trouble connecting right now. Please try again in a moment.';
        }

        // Persist assistant reply to DB
        child_db_query_shard_prepared($sid,
            "INSERT INTO content_chat_log (user_id, content_id, role, message) VALUES (?, ?, 'assistant', ?)",
            [$uid, $content_id, $reply]
        );

        // Log for analytics
        log_user_history($uid, $sid, 'content_chat', [
            'content_id' => $content_id,
            'content_title' => $content_item['title'] ?? '',
            'user_tier' => $current_tier,
            'user_stage' => $current_stage,
        ]);

        echo json_encode(['results' => ['reply' => $reply, 'content_id' => $content_id]]);
        exit;
    }

    if (count($errs) > 0) {
        echo json_encode(['error' => implode(', ', $errs)]);
        exit;
    }
}

// ── First-chat choice with response caching ──
// Uses cached AI responses when available; generates + caches when not.
// Cache key: (content_id, choice_key, content_version)
if (($_POST['action'] ?? '') === 'content_chat_choice') {
    $errs = [];
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    $content_id = trim($_POST['content_id'] ?? '');
    $choice_key = trim($_POST['choice_key'] ?? '');
    $choice_label = trim($_POST['choice_label'] ?? '');
    $content_version = (int)($_POST['content_version'] ?? 1);

    if (!$uid) { $errs['auth'] = 'Login required.'; }
    if (!$content_id) { $errs['content'] = 'Content ID required.'; }
    if (!$choice_key) { $errs['choice'] = 'Choice key required.'; }

    $content_item = null;
    if (count($errs) <= 0) {
        $catalog = get_content_catalog();
        $items = isset($catalog['contentCatalog']) ? $catalog['contentCatalog'] : (isset($catalog['items']) ? $catalog['items'] : $catalog);
        foreach ($items as $ci) {
            if (($ci['id'] ?? '') === $content_id) {
                $content_item = $ci;
                break;
            }
        }
        if (!$content_item) {
            $errs['content'] = 'Content item not found.';
        }
    }

    if (count($errs) <= 0) {
        $reply = null;

        // ── Check cache first ──
        $cache_row = child_db_query_shard_prepared($sid,
            "SELECT response FROM content_guide_cache WHERE content_id = ? AND choice_key = ? AND content_version = ? LIMIT 1",
            [$content_id, $choice_key, $content_version]
        );
        if ($cache_row) {
            $cached = $cache_row->fetch(PDO::FETCH_ASSOC);
            if ($cached) {
                $reply = $cached['response'];
            }
        }

        // ── Cache miss — generate via AI ──
        if (!$reply) {
            global $ai_config;

            $content_md = get_content_markdown($content_item);

            // Build a focused system prompt for the choice
            $system_prompt  = "You are a friendly, knowledgeable content guide for the " . CHILD_APP_NAME . " learning system.\n\n";
            $system_prompt .= "═══ CONTENT ITEM ═══\n";
            $system_prompt .= "Title: " . ($content_item['title'] ?? '') . "\n";
            $system_prompt .= "Type: " . ($content_item['type'] ?? '') . "\n";
            $system_prompt .= "Description: " . ($content_item['description'] ?? '') . "\n";
            $system_prompt .= "Dimensions: " . implode(', ', (array)($content_item['dimensions'] ?? [])) . "\n";
            $system_prompt .= "Target Stages: " . implode(', ', (array)($content_item['stages'] ?? [])) . "\n";
            $system_prompt .= "Duration: " . ($content_item['estimatedDuration'] ?? 'unknown') . "\n";
            $system_prompt .= "Tags: " . implode(', ', (array)($content_item['tags'] ?? [])) . "\n";

            if ($content_md) {
                $truncated = mb_substr($content_md, 0, 6000);
                if (mb_strlen($content_md) > 6000) {
                    $truncated .= "\n\n[... content continues — " . mb_strlen($content_md) . " total characters ...]";
                }
                $system_prompt .= "\n═══ FULL CONTENT (from course material) ═══\n";
                $system_prompt .= $truncated . "\n";
            }

            $system_prompt .= "\n═══ INSTRUCTIONS ═══\n";
            $system_prompt .= "The user is seeing this content for the first time and clicked a quick-start button.\n";
            $system_prompt .= "Give a helpful, engaging response based ONLY on the actual content above.\n";
            $system_prompt .= "Keep it concise — 3-5 sentences. Be encouraging and practical.\n";
            $system_prompt .= "Write for a general audience (this response will be reused for multiple users).\n";
            $system_prompt .= "Do NOT reference the user by name, stage, or personal details.\n";
            $system_prompt .= "Never make up content that isn't in the course material.\n";

            $conv = [['role' => 'user', 'content' => $choice_label]];

            try {
                $ai_response = call_ai_api($system_prompt, $conv, $ai_config);
                if ($ai_response && isset($ai_response['content'])) {
                    $reply = $ai_response['content'];
                }
                if (!$reply) {
                    $reply = 'I had trouble processing that. Could you try asking in your own words?';
                }
            } catch (Exception $e) {
                error_log("[content_chat_choice] error: " . $e->getMessage());
                $reply = 'Sorry, I\'m having trouble connecting right now. Please try again in a moment.';
            }

            // ── Store in cache (ignore duplicate key errors) ──
            try {
                child_db_query_shard_prepared($sid,
                    "INSERT INTO content_guide_cache (content_id, choice_key, content_version, response) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE response = VALUES(response), created_at = NOW()",
                    [$content_id, $choice_key, $content_version, $reply]
                );
            } catch (Exception $e) {
                error_log("[content_guide_cache] write error: " . $e->getMessage());
            }
        }

        // ── Persist to user's chat log so it appears in history ──
        child_db_query_shard_prepared($sid,
            "INSERT INTO content_chat_log (user_id, content_id, role, message) VALUES (?, ?, 'user', ?)",
            [$uid, $content_id, $choice_label]
        );
        child_db_query_shard_prepared($sid,
            "INSERT INTO content_chat_log (user_id, content_id, role, message) VALUES (?, ?, 'assistant', ?)",
            [$uid, $content_id, $reply]
        );

        echo json_encode(['results' => ['reply' => $reply, 'content_id' => $content_id, 'cached' => isset($cached)]]);
        exit;
    }

    if (count($errs) > 0) {
        echo json_encode(['error' => implode(', ', $errs)]);
        exit;
    }
}

// ── Load previous chat history for a content item (GET) ──
if (($_GET['action'] ?? '') === 'content_chat_history') {
    $uid = $_SESSION['user_id'] ?? 0;
    $sid = $_SESSION['shard_id'] ?? '';
    $content_id = trim($_GET['content_id'] ?? '');

    if (!$uid || !$content_id) {
        echo json_encode(['results' => ['messages' => []]]);
        exit;
    }

    $rows = child_db_query_shard_prepared($sid,
        "SELECT role, message, created_at FROM content_chat_log WHERE user_id = ? AND content_id = ? ORDER BY created_at ASC LIMIT 50",
        [$uid, $content_id]
    );
    $messages = [];
    if ($rows) {
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $messages[] = [
                'role' => $row['role'],
                'message' => $row['message'],
                'ts' => $row['created_at'],
            ];
        }
    }

    echo json_encode(['results' => ['messages' => $messages]]);
    exit;
}
