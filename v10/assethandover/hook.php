<?php
function plugin_assethandover_install() {
    global $DB;

    $cs = DBConnection::getDefaultCharset();
    $co = DBConnection::getDefaultCollation();
    $ks = DBConnection::getDefaultPrimaryKeySignOption();

    // Table: handovers header
    if (!$DB->tableExists('glpi_plugin_assethandover_handovers')) {
        $DB->queryOrDie("
            CREATE TABLE `glpi_plugin_assethandover_handovers` (
                `id`             int $ks NOT NULL AUTO_INCREMENT,
                `request_type`   varchar(20)  NOT NULL DEFAULT 'handover',
                `users_id`       int NOT NULL DEFAULT 0,
                `department`     varchar(255) DEFAULT NULL,
                `unit`           varchar(255) DEFAULT NULL,
                `position`       varchar(255) DEFAULT NULL,
                `gender`         varchar(20)  DEFAULT NULL,
                `employee_id`    varchar(100) DEFAULT NULL,
                `manager_name`   varchar(255) DEFAULT NULL,
                `assigned_by`    varchar(255) DEFAULT NULL,
                `checked_by`     varchar(255) DEFAULT NULL,
                `handover_date`  date DEFAULT NULL,
                `return_date`    date DEFAULT NULL,
                `remark`         text,
                `user_confirmed` tinyint NOT NULL DEFAULT 0,
                `from_handover_id` int DEFAULT NULL COMMENT 'Source handover record for returns',
                `created_by`     int NOT NULL DEFAULT 0,
                `is_deleted`     tinyint NOT NULL DEFAULT 0,
                `date_creation`  timestamp DEFAULT NULL,
                `date_mod`       timestamp DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `users_id`     (`users_id`),
                KEY `request_type` (`request_type`),
                KEY `is_deleted`   (`is_deleted`)
            ) ENGINE=InnoDB DEFAULT CHARSET=$cs COLLATE=$co
        ", "Cannot create assethandover_handovers");
    } else {
        // Add new columns if upgrading from older version
        $new_cols = [
            'unit'        => "ADD COLUMN `unit`        varchar(255) DEFAULT NULL AFTER `department`",
            'position'    => "ADD COLUMN `position`    varchar(255) DEFAULT NULL AFTER `unit`",
            'gender'      => "ADD COLUMN `gender`      varchar(20)  DEFAULT NULL AFTER `position`",
            'employee_id'  => "ADD COLUMN `employee_id`  varchar(100) DEFAULT NULL AFTER `gender`",
            'manager_name'     => "ADD COLUMN `manager_name`     varchar(255) DEFAULT NULL AFTER `employee_id`",
            'from_handover_id' => "ADD COLUMN `from_handover_id` int DEFAULT NULL AFTER `manager_name`",
        ];
        foreach ($new_cols as $col => $ddl) {
            if (!$DB->fieldExists('glpi_plugin_assethandover_handovers', $col)) {
                $DB->query("ALTER TABLE `glpi_plugin_assethandover_handovers` $ddl");
            }
        }
    }

    // Table: items (one row per asset)
    if (!$DB->tableExists('glpi_plugin_assethandover_items')) {
        $DB->queryOrDie("
            CREATE TABLE `glpi_plugin_assethandover_items` (
                `id`               int $ks NOT NULL AUTO_INCREMENT,
                `handovers_id`     int NOT NULL,
                `asset_id`         int DEFAULT NULL,
                `asset_itemtype`   varchar(100) DEFAULT NULL,
                `asset_name`       varchar(255) DEFAULT NULL,
                `asset_type`       varchar(100) DEFAULT NULL,
                `serial_number`    varchar(255) DEFAULT NULL,
                `asset_number`     varchar(255) DEFAULT NULL,
                `description`      text,
                `condition_issue`  varchar(20)  DEFAULT NULL,
                `condition_return` varchar(20)  DEFAULT NULL,
                `return_status`    varchar(20)  DEFAULT NULL,
                `item_remark`      text,
                `sort_order`       int NOT NULL DEFAULT 0,
                `date_creation`    timestamp DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `handovers_id` (`handovers_id`),
                KEY `asset_id`     (`asset_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=$cs COLLATE=$co
        ", "Cannot create assethandover_items");
    } else {
        // Add new columns if upgrading
        $new_item_cols = [
            'asset_itemtype' => "ADD COLUMN `asset_itemtype` varchar(100) DEFAULT NULL AFTER `asset_id`",
            'description'    => "ADD COLUMN `description`    text AFTER `asset_type`",
            'item_remark'    => "ADD COLUMN `item_remark`    text AFTER `return_status`",
            'serial_number'  => "ADD COLUMN `serial_number`  varchar(255) DEFAULT NULL AFTER `description`",
            'asset_number'   => "ADD COLUMN `asset_number`   varchar(255) DEFAULT NULL AFTER `serial_number`",
        ];
        foreach ($new_item_cols as $col => $ddl) {
            if (!$DB->fieldExists('glpi_plugin_assethandover_items', $col)) {
                $DB->query("ALTER TABLE `glpi_plugin_assethandover_items` $ddl");
            }
        }
    }

    // Activity log table (#7)
    if (!$DB->tableExists('glpi_plugin_assethandover_logs')) {
        $DB->queryOrDie("
            CREATE TABLE `glpi_plugin_assethandover_logs` (
                `id`           int $ks NOT NULL AUTO_INCREMENT,
                `handovers_id` int NOT NULL,
                `users_id`     int DEFAULT NULL,
                `user_name`    varchar(255) DEFAULT NULL,
                `action`       varchar(50)  NOT NULL DEFAULT 'update',
                `field`        varchar(100) DEFAULT NULL,
                `old_value`    text,
                `new_value`    text,
                `note`         text,
                `date_log`     timestamp DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `handovers_id` (`handovers_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=$cs COLLATE=$co
        ", "Cannot create assethandover_logs");
    }

    // Grant rights to Super-Admin only on fresh install; preserve on reinstall
    foreach (getAllDataFromTable('glpi_profiles') as $profile) {
        $pid = $profile['id'];
        if (countElementsInTable('glpi_profilerights', ['profiles_id' => $pid, 'name' => 'plugin_assethandover'])) {
            continue;
        }
        if ($profile['name'] === 'Super-Admin' || $pid == 4) {
            $DB->insert('glpi_profilerights', [
                'profiles_id' => $pid,
                'name'        => 'plugin_assethandover',
                'rights'      => READ | CREATE | UPDATE | DELETE | PURGE,
            ]);
        }
    }

    return true;
}

function plugin_assethandover_uninstall() {
    // Data is PRESERVED on uninstall — tables are NOT dropped
    // Rights are PRESERVED so assignments survive reinstall cycles
    // Only clear GLPI session cache so menu disappears immediately
    if (isset($_SESSION['glpimenu']))     unset($_SESSION['glpimenu']);
    if (isset($_SESSION['glpi_plugins'])) unset($_SESSION['glpi_plugins']);
    return true;
}
