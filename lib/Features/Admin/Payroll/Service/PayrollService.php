<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\Payroll\Service;

use OCA\StechTimesheet\Features\Admin\Payroll\Db\PayrollMapper;

class PayrollService {
    private $mapper;

    public function __construct(PayrollMapper $mapper) {
        $this->mapper = $mapper;
    }

    public function getSettings(): array {
        return $this->mapper->getPayrollSettings();
    }

    public function saveSettings(array $settings): void {
        $allowedKeys = ['pay_enabled', 'pay_frequency', 'pay_start_date', 'pay_date_1', 'pay_date_2', 'pay_color'];
        foreach ($settings as $key => $value) {
            if (in_array($key, $allowedKeys)) {
                $this->mapper->saveSetting($key, (string)$value);
            }
        }
    }
}