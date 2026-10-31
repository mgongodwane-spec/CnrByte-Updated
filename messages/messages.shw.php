<?php
declare(strict_types=1);

function show_client_search_form(mysqli $conn): void {
    ?>
    <form method="get" action="">
        <label for="search_by">Search by:</label>
        <select name="search_by" id="search_by" onchange="updatePlaceholder()">
            <option value="username">Username</option>
            <option value="email">Email</option>
            <option value="phone">Cellphone</option>
        </select>

        <input type="text" name="search_value" id="search_value" placeholder="Enter username" required>
        <button type="submit">Search</button>
    </form>

    <script>
    function updatePlaceholder() {
        const select = document.getElementById('search_by');
        const input = document.getElementById('search_value');
        const field = select.value;

        if (field === 'username') {
            input.placeholder = 'Enter username';
            input.type = 'text';
        } else if (field === 'email') {
            input.placeholder = 'Enter email';
            input.type = 'email';
        } else if (field === 'phone') {
            input.placeholder = 'Enter cellphone number';
            input.type = 'tel';
        }
    }
    </script>
    <?php
    search_client($conn);
    echo "<div style='margin-top:24px;'>";
    show_all_contacts($conn);
    echo "</div>";
}



function show_messages_table(mysqli $conn): void {
    display_success_error_message();
    echo "<h3 class='form-title'>Messages</h3>";

    // If messages table exists, list recent messages, otherwise placeholder
    $check = $conn->query("SHOW TABLES LIKE 'messages'");
    if ($check && $check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT id, subject, sender_id, receiver_id, is_read, message, created_at FROM messages ORDER BY created_at DESC LIMIT 50");
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                echo "<p>No messages yet.</p>";
            } else {
                echo "<table class='products_tbl'><tr><th>ID</th><th>Subject</th><th>Sender</th><th>Receiver</th><th>Status</th><th>Preview</th><th>Date</th></tr>";
                while ($r = $res->fetch_assoc()) {
                    $sub = htmlspecialchars((string)$r['subject'], ENT_QUOTES, 'UTF-8');
                    $sd = htmlspecialchars((string)$r['sender_id'], ENT_QUOTES, 'UTF-8');
                    $rc = htmlspecialchars((string)$r['receiver_id'], ENT_QUOTES, 'UTF-8');
                    $status = (int)($r['is_read'] ?? 0) === 1 ? 'Read' : 'Unread';
                    $preview = htmlspecialchars(mb_strimwidth((string)$r['message'], 0, 60, '...'), ENT_QUOTES, 'UTF-8');
                    $dt = htmlspecialchars((string)$r['created_at'], ENT_QUOTES, 'UTF-8');
                    echo "<tr><td>{$r['id']}</td><td>{$sub}</td><td>{$sd}</td><td>{$rc}</td><td>{$status}</td><td>{$preview}</td><td>{$dt}</td></tr>";
                }
                echo "</table>";
            }
            $stmt->close();
        }
    } else {
        echo "<p>Messages table not found. Use the messaging feature when available.</p>";
    }
}

function create_message_form(mysqli $conn): void {
    display_success_error_message();
    $contact = $_SESSION['contact_info'] ?? null;
    ?>
    <?php if (!$contact): ?>
        <div class="signup-form">
            <h3 class="form-title">New Message</h3>
            <p>Select a contact from search results or your contacts list first.</p>
            <a href="/cnrbyte/messages/dashboard.pge.php" class="form-btn" style="text-decoration:none;">Find Contact</a>
        </div>
    <?php else: ?>
        <form action="/cnrbyte/messages/create.run.php" method="post" class="signup-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <h3 class="form-title">New Message</h3>
            <p><strong>To:</strong> <?php echo htmlspecialchars((string)($contact['contact_username'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars((string)($contact['contact_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Cellphone:</strong> <?php echo htmlspecialchars((string)($contact['contact_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>

            <input type="hidden" name="receiver_id" value="<?php echo (int)($contact['contact_id'] ?? 0); ?>">

            <label for="subject">Subject</label>
            <input type="text" name="subject" value="Direct Message" required><br>

            <label for="message">Message</label>
            <textarea name="message" rows="6" style="width:100%;" required></textarea><br>

            <button name="send_message" type="submit" class="form-btn">Send Message</button>
            <a href="/cnrbyte/messages/chat.pge.php" class="form-btn" style="text-decoration:none;">Open Chat</a>
        </form>
    <?php endif; ?>
    <?php
}

function edit_message_form(): void {
    echo "<p>Edit message form placeholder.</p>";
}
