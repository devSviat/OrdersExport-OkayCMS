<?php


namespace Okay\Modules\Sviat\OrdersExport\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;

/**
 * Контролер адмін-панелі для налаштувань експорту замовлень
 */
class OrdersExportAdmin extends IndexAdmin
{
    /**
     * Відображає сторінку налаштувань експорту замовлень
     */
    public function fetch() {
        if ($this->request->method('post')) {
            $ordersCount = $this->request->post('orders_count', 'integer', 100);
            $ordersCount = max(20, min(1000, $ordersCount));

            $delimiter = (string) $this->request->post('column_delimiter');
            if ($delimiter === 'tab') {
                $delimiter = "\t";
            } elseif (!in_array($delimiter, [';', ','], true)) {
                $delimiter = ';';
            }

            $defaultExportTtn = (string) $this->request->post('default_export_ttn');
            if (!in_array($defaultExportTtn, ['0', '1', '2'], true)) {
                $defaultExportTtn = '0';
            }

            $showOrdersBrandFilter = $this->request->post('show_orders_brand_filter', 'boolean') ? 1 : 0;

            $this->settings->set('sviat__orders_export__orders_count', $ordersCount);
            $this->settings->set('sviat__orders_export__column_delimiter', $delimiter);
            $this->settings->set('sviat__orders_export__default_export_ttn', $defaultExportTtn);
            $this->settings->set('sviat__orders_export__show_orders_brand_filter', $showOrdersBrandFilter);

            $this->design->assign('message_success', 'saved');
        }

        $columnDelimiter = (string) $this->settings->get('sviat__orders_export__column_delimiter');
        if (!in_array($columnDelimiter, [';', ',', "\t"], true)) {
            $columnDelimiter = ';';
        }

        $ordersCount = (int) $this->settings->get('sviat__orders_export__orders_count');
        if ($ordersCount <= 0) {
            $ordersCount = 100;
        }

        $defaultExportTtn = (string) $this->settings->get('sviat__orders_export__default_export_ttn');
        if (!in_array($defaultExportTtn, ['0', '1', '2'], true)) {
            $defaultExportTtn = '0';
        }

        $showOrdersBrandFilter = (int) $this->settings->get('sviat__orders_export__show_orders_brand_filter');

        $this->design->assign('orders_export_orders_count', $ordersCount);
        $this->design->assign('orders_export_column_delimiter', $columnDelimiter);
        $this->design->assign('orders_export_default_export_ttn', $defaultExportTtn);
        $this->design->assign('orders_export_show_orders_brand_filter', $showOrdersBrandFilter);

        $this->response->setContent($this->design->fetch('export_orders_admin.tpl'));
    }
}
