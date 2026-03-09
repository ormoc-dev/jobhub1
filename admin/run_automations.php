<?php
// Run automation rules (cleanup) - intended to be run from CLI or scheduled task
require_once __DIR__ . '/../config.php';

// Simple file logger
function logMsg($msg) {
    $logFile = __DIR__ . '/automation.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

try {
    $rules = $pdo->query("SELECT * FROM automation_rules WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rules as $rule) {
        $conditions = json_decode($rule['conditions'], true) ?: [];
        $actions = json_decode($rule['actions'], true) ?: [];

        if (($rule['rule_type'] ?? '') !== 'cleanup') continue;
        if (($conditions['entity'] ?? '') !== 'users') continue;

        $inactiveDays = (int)($conditions['inactive_days'] ?? 730);
        $roles = $conditions['roles'] ?? [];

        // Build select for users to remove
        if (!empty($roles) && is_array($roles)) {
            $placeholders = implode(',', array_fill(0, count($roles), '?'));
            $sql = "SELECT id FROM users WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? DAY) AND role IN ($placeholders)";
            $params = array_merge([$inactiveDays], $roles);
        } else {
            $sql = "SELECT id FROM users WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $params = [$inactiveDays];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $deleted = 0;
        foreach ($userIds as $uid) {
            // delete user; foreign keys with ON DELETE CASCADE will remove related data
            $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($del->execute([$uid])) {
                $deleted++;
                logMsg("Rule({$rule['id']}) deleted user id={$uid}");
            }
        }

        // Update rule last executed metadata
        $update = $pdo->prepare("UPDATE automation_rules SET last_executed_at = NOW(), execution_count = execution_count + 1 WHERE id = ?");
        $update->execute([$rule['id']]);

        logMsg("Executed rule id={$rule['id']} name='{$rule['rule_name']}' - removed {$deleted} users.");
    }

    echo "Automation run complete. Check admin/automation.log for details.\n";
} catch (Exception $e) {
    logMsg("Error: " . $e->getMessage());
    echo "Error running automations: " . $e->getMessage() . "\n";
}

?>
