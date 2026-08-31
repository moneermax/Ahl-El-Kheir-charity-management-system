<?php
require_once 'config/messaging.php';

// Test 1: Count functions should return 0 (no data yet)
echo "Unread count for user 1: " . get_unread_count(1, 'admin') . "<br>";
echo "Notifications for user 1: " . count(get_notifications(1)) . "<br>";
echo "Messages for user 1: " . count(get_messages(1, 'admin')) . "<br>";

// Test 2: Send a system notification
$id = send_system_notification(1, 'Test Title', 'This is a test notification', 'info');
echo "Sent notification ID: " . ($id ?: 'FAILED') . "<br>";

// Test 3: Send a direct message (requires two different user IDs)
// $id2 = send_user_message(1, 2, 'Test Subject', 'Hello from the messaging system');
// echo "Sent message ID: " . ($id2 ?: 'FAILED') . "<br>";

// Test 4: Check unread count again
echo "Unread count after insert: " . get_unread_count(1, 'admin') . "<br>";