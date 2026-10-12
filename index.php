
<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
$file = __DIR__ . "/messages.json";

// Create the file if it does not exist
if (!file_exists($file)) {
    file_put_contents($file, "[]");
}

// Return messages as JSON for automatic updates
if (isset($_GET["action"]) && $_GET["action"] === "fetch") {
    header("Content-Type: application/json; charset=UTF-8");

    $messages = json_decode(file_get_contents($file), true);
    echo json_encode(is_array($messages) ? $messages : []);
    exit;
}

// Send a message
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if ($username !== "" && $message !== "") {
        $fp = fopen($file, "c+");

        if ($fp) {
            flock($fp, LOCK_EX);

            $contents = stream_get_contents($fp);
            $messages = json_decode($contents ?: "[]", true);

            if (!is_array($messages)) {
                $messages = [];
            }

            $messages[] = [
                "username" => mb_substr($username, 0, 50),
                "message" => mb_substr($message, 0, 1000),
                "sent_at" => date("Y-m-d H:i:s")
            ];

            // Keep only the latest 500 messages
            $messages = array_slice($messages, -500);

            rewind($fp);
            ftruncate($fp, 0);
            fwrite(
                $fp,
                json_encode(
                    $messages,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                )
            );
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Chat Application</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #eaf0f6;
            margin: 0;
            padding: 20px;
        }

        .chat-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px #0002;
        }

        h2 {
            background: #2563eb;
            color: white;
            padding: 20px;
            margin: 0;
            text-align: center;
        }

        .messages {
            height: 350px;
            overflow-y: auto;
            padding: 15px;
        }

        .message {
            background: #f1f5f9;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            overflow-wrap: anywhere;
        }

        .message strong {
            color: #2563eb;
        }

        .message small {
            display: block;
            color: #64748b;
            margin-top: 5px;
        }

        .chat-form {
            padding: 15px;
            border-top: 1px solid #ddd;
        }

        input, button {
            width: 100%;
            padding: 12px;
            margin-bottom: 10px;
            font-size: 16px;
        }

        input {
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .status {
            color: #64748b;
            font-size: 12px;
            text-align: center;
            padding: 8px;
        }
    </style>
</head>

<body>

<div class="chat-container">
    <h2>💬 Chat Application</h2>

    <div class="messages" id="messages">
        Loading messages...
    </div>

    <div class="status" id="status">Connecting...</div>

    <form method="post" class="chat-form">
        <input
            type="text"
            name="username"
            placeholder="Enter your name"
            maxlength="50"
            required
        >

        <input
            type="text"
            name="message"
            placeholder="Type your message..."
            maxlength="1000"
            required
        >

        <button type="submit">Send Message</button>
    </form>
</div>

<script>
const messagesBox = document.getElementById("messages");
const statusBox = document.getElementById("status");

let previousCount = -1;

async function loadMessages() {
    try {
        const response = await fetch("index.php?action=fetch", {
            cache: "no-store"
        });

        if (!response.ok) {
            throw new Error("Could not load messages");
        }

        const messages = await response.json();
        const shouldScroll =
            previousCount === -1 ||
            messagesBox.scrollHeight -
            messagesBox.scrollTop -
            messagesBox.clientHeight < 50;

        messagesBox.replaceChildren();

        messages.forEach(function(msg) {
            const card = document.createElement("div");
            card.className = "message";

            const username = document.createElement("strong");
            username.textContent = msg.username;

            const text = document.createElement("p");
            text.textContent = msg.message;

            const time = document.createElement("small");
            time.textContent = msg.sent_at;

            card.append(username, text, time);
            messagesBox.appendChild(card);
        });

        if (messages.length === 0) {
            messagesBox.textContent = "No messages yet. Start chatting!";
        }

        if (shouldScroll) {
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }

        previousCount = messages.length;
        statusBox.textContent = "Connected";
    } catch (error) {
        statusBox.textContent = "Connection error. Retrying...";
    }
}

loadMessages();
setInterval(loadMessages, 2000);
</script>

</body>
</html>