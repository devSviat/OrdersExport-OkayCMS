{$meta_title='Експорт замовлень' scope=global}

{*Название страницы*}
<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="heading_page">Експорт замовлень</div>
    </div>
</div>

{*Вывод ошибок*}
{if $message_error}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--error">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_error == 'no_permission'}
                        Немає прав доступу до директорії {$export_files_dir}
                        {else}
                        {$message_error|escape}
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}

{if $message_error != 'no_permission'}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--icon">
                <div class="alert__content">
                    <div class="alert__title">Опис</div>
                    <p>Експорт замовлень у форматі CSV. Кожен товар з замовлення буде виведений окремим рядком з номером замовлення, статусом, SKU та назвою товару.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="success_export" class="" style="display: none">
        <div class="alert alert--icon alert--success">
            <div class="alert__content">
                <div class="alert__title">Експорт успішно завершено</div>
            </div>
        </div>
    </div>

    {*Параметры элемента*}
    <div class="boxed fn_toggle_wrap">
        <div class="row">
            <div class="col-lg-12 col-md-12 ">
                <progress id="progressbar" class="progress progress-info mt-0" style="display: none" value="0" max="100"></progress>
            </div>
            <div class="col-lg-12 col-md-12 ">
                <div id="fn_start" class="">
                    <div class="row">
                        {if $statuses}
                        <div class="col-md-3 col-sm-3 col-lg-3 col-sm-12 mb-h">
                            <div class="option_export_wrap">
                                <div class="heading_label">Статус замовлення</div>
                                <select class="selectpicker form-control" name="status" id="status_filter">
                                    <option value="">Всі статуси</option>
                                    {foreach $statuses as $status}
                                        <option value="{$status->id}">{$status->name|escape}</option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>
                        {/if}
                        {if $is_nova_poshta_tracking_active}
                        <div class="col-md-3 col-sm-3 col-lg-3 col-sm-12 mb-h">
                            <div class="option_export_wrap">
                                <div class="heading_label">Експортувати ТТН</div>
                                <select class="selectpicker form-control" name="export_ttn" id="export_ttn">
                                    <option value="0">Не додавати</option>
                                    <option value="1">Додати ТТН</option>
                                    <option value="2">Тільки з ТТН</option>
                                </select>
                            </div>
                        </div>
                        {/if}
                        <div class="col-md-3 col-sm-3 col-lg-3 col-sm-12 float-sm-right mt-2">
                            <button id="fn_start_export" type="button" class="btn btn_small btn_blue float-md-right">
                                {include file='svg_icon.tpl' svgId='export'}
                                <span>Експортувати</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}

<script src="{$rootUrl}/backend/design/js/piecon/piecon.js"></script>
<script>
    {literal}
    var in_process=false;

    $(function() {
        $('button#fn_start_export').click(function() {
            if (in_process) {
                return false;
            }
            in_process = true;
            
            Piecon.setOptions({fallback: 'force'});
            Piecon.setProgress(0);
            var progress_item = $("#progressbar");
            progress_item.show();

            var status = $('#status_filter').val() || null;
            var export_ttn = $('#export_ttn').val() || '0';

            do_export('', progress_item, status, export_ttn);
        });

        function do_export(page, progress, status, export_ttn)
        {
            page = typeof(page) != 'undefined' ? page : 1;
            var data = {page: page};
            if (status) {
                data.status = status;
            }
            if (export_ttn) {
                data.export_ttn = export_ttn;
            }
            
            $.ajax({
                url: "{/literal}{url_generator route="Sviat_OrdersExport_exportOrders" absolute=1}{literal}",
                data: data,
                dataType: 'json',
                success: function(data){
                    if(data && !data.end)
                    {
                        Piecon.setProgress(Math.round(100*data.page/data.totalpages));
                        progress.attr('value',100*data.page/data.totalpages);
                        do_export(data.page*1+1, progress, status, export_ttn);
                    }
                    else
                    {
                        if(data && data.end)
                        {
                            Piecon.setProgress(100);
                            progress.attr('value','100');
                            window.location.href = 'files/export/export_orders_enhanced.csv';
                            progress.fadeOut(500);
                            $('#success_export').show();
                            in_process = false;
                        }
                    }
                },
                error:function(xhr, status, errorThrown) {
                    alert(errorThrown+'\n'+xhr.responseText);
                    in_process = false;
                }
            });
        }
    });
    {/literal}
</script>
