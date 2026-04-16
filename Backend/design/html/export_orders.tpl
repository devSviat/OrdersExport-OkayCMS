{$meta_title=$btr->sviat__orders_export__title scope=global}

<div class="main_header">
    <div class="main_header__item">
        <div class="main_header__inner">
            <div class="box_heading heading_page">{$btr->sviat__orders_export__title|escape}</div>
        </div>
    </div>
    <div class="main_header__item">
        <div class="main_header__inner">
            <a href="{$root_url}/backend/index.php?controller=OrdersExportAdmin"
                class="btn btn_small btn_blue">{$btr->sviat__orders_export__to_settings|escape}</a>
        </div>
    </div>
</div>

{* Блок помилок *}
{if $message_error}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--error">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_error == 'no_permission'}
                        {$btr->sviat__orders_export__no_permission|escape} {$export_files_dir}
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
                    <div class="alert__title">{$btr->sviat__orders_export__description_title|escape}</div>
                    <p>{$btr->sviat__orders_export__description_text|escape}</p>
                </div>
            </div>
        </div>
    </div>

    <div id="success_export" class="" style="display: none">
        <div class="alert alert--icon alert--success">
            <div class="alert__content">
                <div class="alert__title">{$btr->sviat__orders_export__success|escape}</div>
            </div>
        </div>
    </div>

    {* Параметри експорту *}
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
                                <div class="heading_label">{$btr->sviat__orders_export__status|escape}</div>
                                <select class="selectpicker form-control" name="status" id="status_filter">
                                    <option value="">{$btr->sviat__orders_export__all_statuses|escape}</option>
                                    {foreach $statuses as $status}
                                        <option value="{$status->id}">{$status->name|escape}</option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>
                        {/if}
                        {if $brands}
                        <div class="col-md-3 col-sm-3 col-lg-3 col-sm-12 mb-h">
                            <div class="option_export_wrap">
                                <div class="heading_label">{$btr->sviat__orders_export__brands|escape}</div>
                                <select class="selectpicker form-control" name="brand_ids[]" id="brands_filter" multiple data-selected-text-format="count" data-live-search="true" title="{$btr->sviat__orders_export__all_brands|escape}">
                                    {foreach $brands as $brand}
                                        <option value="{$brand->id}">{$brand->name|escape}</option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>
                        {/if}
                        {if $is_nova_poshta_tracking_active}
                        <div class="col-md-3 col-sm-3 col-lg-3 col-sm-12 mb-h">
                            <div class="option_export_wrap">
                                <div class="heading_label">{$btr->sviat__orders_export__export_ttn|escape}</div>
                                <select class="selectpicker form-control" name="export_ttn" id="export_ttn">
                                    <option value="0" {if $orders_export_default_export_ttn == '0'}selected{/if}>{$btr->sviat__orders_export__ttn_off|escape}</option>
                                    <option value="1" {if $orders_export_default_export_ttn == '1'}selected{/if}>{$btr->sviat__orders_export__ttn_add|escape}</option>
                                    <option value="2" {if $orders_export_default_export_ttn == '2'}selected{/if}>{$btr->sviat__orders_export__ttn_only|escape}</option>
                                </select>
                            </div>
                        </div>
                        {/if}
                        <div class="col-md-3 col-sm-3 col-lg-3 col-sm-12 float-sm-right mt-2">
                            <button id="fn_start_export" type="button" class="btn btn_small btn_blue float-md-right">
                                {include file='svg_icon.tpl' svgId='export'}
                                <span>{$btr->sviat__orders_export__export_btn|escape}</span>
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
    (function () {
        var inProcess = false;

        function buildExportData(page, status, exportTtn, brandIds) {
            var data = {page: page || 1};
            if (status) {
                data.status = status;
            }
            if (exportTtn) {
                data.export_ttn = exportTtn;
            }
            if (Array.isArray(brandIds) && brandIds.length > 0) {
                data.brand_ids = brandIds;
            }
            return data;
        }

        function exportPage(page, progress, status, exportTtn, brandIds) {
            return $.ajax({
                url: "{/literal}{url_generator route="Sviat_OrdersExport_exportOrders" absolute=1}{literal}",
                data: buildExportData(page, status, exportTtn, brandIds),
                dataType: 'json'
            }).then(function (data) {
                if (data && data.error) {
                    throw new Error(data.error);
                }
                return data;
            });
        }

        $(function() {
            var $startBtn = $('button#fn_start_export');
            var $progress = $("#progressbar");

            $startBtn.on('click', function() {
                if (inProcess) {
                    return false;
                }

                inProcess = true;
                $startBtn.prop('disabled', true);
                $('#success_export').hide();

                Piecon.setOptions({fallback: 'force'});
                Piecon.setProgress(0);

                $progress.attr('value', 0).show();

                var status = $('#status_filter').val() || null;
                var exportTtn = $('#export_ttn').val() || '0';
                var brandIds = $('#brands_filter').val() || [];

                (function run(page) {
                    exportPage(page, $progress, status, exportTtn, brandIds)
                        .done(function (data) {
                            if (data && !data.end) {
                                var percent = Math.round(100 * data.page / data.totalpages);
                                Piecon.setProgress(percent);
                                $progress.attr('value', percent);
                                run((data.page * 1) + 1);
                                return;
                            }

                            if (data && data.end) {
                                Piecon.setProgress(100);
                                $progress.attr('value', 100);
                                window.location.href = 'files/export/export_orders_enhanced.csv';
                                $progress.fadeOut(500);
                                $('#success_export').show();
                            }

                            inProcess = false;
                            $startBtn.prop('disabled', false);
                        })
                        .fail(function (xhr) {
                            var msg = (xhr && xhr.responseJSON && xhr.responseJSON.error)
                                ? xhr.responseJSON.error
                                : ((xhr && xhr.responseText) ? xhr.responseText : 'Export error');
                            alert(msg);

                            inProcess = false;
                            $startBtn.prop('disabled', false);
                        });
                })(1);
            });
        });
    })();
    {/literal}
</script>
