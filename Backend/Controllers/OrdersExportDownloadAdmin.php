<?php

namespace Okay\Modules\Sviat\OrdersExport\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Modules\Sviat\OrdersExport\Backend\Helpers\BackendOrdersExportHelper;

/**
 * Віддає готовий CSV експорту замовлень.
 *
 * Через backend/files/index.php це не працює: там білий список
 * BackendFileDownloadPolicy, у якому лише файли ядра, і наш
 * export_orders_enhanced.csv у нього не входить — замість файлу відкривалась
 * порожня сторінка. Модуль віддає свій файл сам, а право на завантаження
 * лишається тим самим ('export' у Init).
 */
class OrdersExportDownloadAdmin extends IndexAdmin
{
    public function fetch(BackendOrdersExportHelper $backendOrdersExportHelper)
    {
        $configParams = $backendOrdersExportHelper->getConfigParams();
        $filename = basename((string) $configParams->filename);
        // Робочий каталог бекенду — корінь проєкту (backend/index.php робить chdir('..')).
        $path = realpath($configParams->export_files_dir . $filename);

        if ($path === false || !is_file($path)) {
            $this->response->setStatusCode(404);
            $this->response->setContent('', RESPONSE_TEXT);
            return;
        }

        $this->response->setContentType(RESPONSE_TEXT);
        $this->response->addHeader('Content-Description: File Transfer');
        $this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
        $this->response->addHeader('Content-Length: ' . filesize($path));
        $this->response->addHeader('Cache-Control: must-revalidate');
        $this->response->addHeader('Pragma: public');
        $this->response->addHeader('Expires: 0');
        $this->response->sendHeaders();

        // Після sendHeaders(): адаптер відповіді додає свій Content-Type останнім
        // і перебив би будь-який, виставлений через addHeader().
        // Кодування — те, у яке exportRun() конвертує файл наприкінці експорту.
        header('Content-Type: text/csv; charset=windows-1251');

        readfile($path);
        exit();
    }
}
