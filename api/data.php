<?php
// api/data.php — JSON API Endpoint
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once '../config/database.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? 'summary';

try {
    switch ($action) {
        case 'summary':
            $total = (int)$pdo->query("SELECT COUNT(*) FROM v_project_full")->fetchColumn();
            $byStatus = $pdo->query("SELECT status_name, COUNT(*) as total FROM v_project_full GROUP BY status_name")->fetchAll();
            $byCategory = $pdo->query("SELECT category_name, COUNT(*) as total FROM v_project_full WHERE category_name IS NOT NULL GROUP BY category_name ORDER BY total DESC")->fetchAll();
            $catOrder = categoryOrder();
            usort($byCategory, function($a, $b) use ($catOrder) {
                $ia = array_search($a['category_name'], $catOrder);
                $ib = array_search($b['category_name'], $catOrder);
                $ia = $ia === false ? 999 : $ia;
                $ib = $ib === false ? 999 : $ib;
                return $ia - $ib;
            });
            $byBenefit = $pdo->query("SELECT benefit_name, COUNT(*) as total FROM v_project_full WHERE benefit_name IS NOT NULL GROUP BY benefit_name ORDER BY total DESC")->fetchAll();
            $fin = $pdo->query("SELECT COALESCE(SUM(cb.planned_cost), 0) as total_budget, COALESCE(SUM(cb.actual_cost), 0) as total_cost, COALESCE(SUM(cb.planned_cost), 0) - COALESCE(SUM(cb.actual_cost), 0) as total_profit FROM cost_breakdown cb")->fetch();
            $stagnant = (int)$pdo->query("SELECT COUNT(*) FROM v_project_full WHERE days_since_last_update > 60 AND status_name IN ('Open Expansion','Open Research')")->fetchColumn();
            $recent = $pdo->query("SELECT id, project_name, status_name, pic, start_date FROM v_project_full ORDER BY start_date DESC LIMIT 10")->fetchAll();

            echo json_encode([
                'ok' => true,
                'total' => $total,
                'financials' => $fin,
                'by_status' => $byStatus,
                'by_category' => $byCategory,
                'by_benefit' => $byBenefit,
                'stagnant_count' => $stagnant,
                'recent' => $recent
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'projects':
            $status = $_GET['status'] ?? '';
            $sql = "SELECT * FROM v_project_full WHERE 1=1";
            $params = [];
            if ($status) { $sql .= " AND status_name = :status"; $params[':status'] = $status; }
            $sql .= " ORDER BY start_date DESC LIMIT 100";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
            break;

        case 'pic':
            $data = $pdo->query("SELECT pic, COUNT(*) as total, SUM(CASE WHEN status_name IN ('Close Finish','Close Hold','Close Reject') THEN 1 ELSE 0 END) as closed, ROUND(AVG(DATEDIFF(end_date, start_date)),1) as avg_duration FROM v_project_full WHERE pic IS NOT NULL AND pic != '' GROUP BY pic ORDER BY total DESC")->fetchAll();
            echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'budget':
            $data = $pdo->query("SELECT project_name, pic, actual_cost, planned_budget, profit FROM v_project_full WHERE planned_budget > 0 ORDER BY profit DESC")->fetchAll();
            echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'anomalies':
            $stagnant = $pdo->query("SELECT COUNT(*) FROM v_project_full WHERE days_since_last_update > 60 AND status_name IN ('Open Expansion','Open Research')")->fetchColumn();
            $noActivity = $pdo->query("SELECT COUNT(*) FROM projects p LEFT JOIN project_activities pa ON p.id = pa.project_id WHERE pa.id IS NULL")->fetchColumn();
            $invalidDate = $pdo->query("SELECT COUNT(*) FROM v_project_full WHERE start_date > end_date AND start_date IS NOT NULL AND end_date IS NOT NULL")->fetchColumn();
            $nullPic = $pdo->query("SELECT COUNT(*) FROM v_project_full WHERE pic IS NULL OR pic = ''")->fetchColumn();
            echo json_encode([
                'ok' => true,
                'stagnant' => (int)$stagnant,
                'no_activity' => (int)$noActivity,
                'invalid_dates' => (int)$invalidDate,
                'null_pic' => (int)$nullPic
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Invalid action. Available: summary, projects, pic, budget, anomalies']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
