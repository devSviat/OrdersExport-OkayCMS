{if $settings->get('sviat__orders_export__show_orders_brand_filter')}
    <div class="main_header__item main_header__item--sort_date sviat_orders_brand_filter_item">
        <div class="main_header__inner">
            <button type="button"
                class="btn btn_small sviat_orders_brand_filter_btn"
                data-toggle="modal"
                data-target="#fn_orders_brand_filter_modal"
                aria-label="{$btr->sviat__orders_export__orders_brand_filter_modal_title|escape}">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-filter"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 4h16v2.172a2 2 0 0 1 -.586 1.414l-4.414 4.414v7l-6 2v-8.5l-4.48 -4.928a2 2 0 0 1 -.52 -1.345v-2.227" /></svg>
            </button>
        </div>
    </div>

    <div id="fn_orders_brand_filter_modal" class="modal fade" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="get" id="fn_orders_brand_filter_form">
                    <input type="hidden" name="controller" value="OrdersAdmin">
                    {if $smarty.get.from_date}
                        <input type="hidden" name="from_date" value="{$smarty.get.from_date|escape}">
                    {/if}
                    {if $smarty.get.to_date}
                        <input type="hidden" name="to_date" value="{$smarty.get.to_date|escape}">
                    {/if}
                    {if $smarty.get.status}
                        <input type="hidden" name="status" value="{$smarty.get.status|escape}">
                    {/if}
                    {if $smarty.get.label}
                        <input type="hidden" name="label" value="{$smarty.get.label|escape}">
                    {/if}
                    {if $smarty.get.keyword}
                        <input type="hidden" name="keyword" value="{$smarty.get.keyword|escape}">
                    {/if}
                    {if $smarty.get.np_status}
                        <input type="hidden" name="np_status" value="{$smarty.get.np_status|escape}">
                    {/if}
                    {if $smarty.get.user_id}
                        <input type="hidden" name="user_id" value="{$smarty.get.user_id|escape}">
                    {/if}
                    <input type="hidden" name="brand_ids" id="fn_orders_brand_ids_value" value="{$smarty.get.brand_ids|escape}">

                    <div class="modal-body">
                        <div class="sviat_obf_modal_head">
                            <button type="button"
                                class="sviat_obf_modal_close"
                                data-dismiss="modal"
                                aria-label="{$btr->sviat__orders_export__cancel|escape}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                            </button>
                            <div class="sviat_obf_modal_heading">{$btr->sviat__orders_export__orders_brand_filter_modal_title|escape}</div>
                        </div>

                        {get_brands var=orders_export_all_brands visible=1}
                        <select
                            class="selectpicker form-control"
                            id="fn_orders_brand_select"
                            multiple
                            data-selected-text-format="count"
                            data-live-search="true"
                            title="{$btr->sviat__orders_export__all_brands|escape}"
                            data-selected-brand-ids="{$smarty.get.brand_ids|escape}"
                        >
                            {foreach $orders_export_all_brands as $brand}
                                <option value="{$brand->id}">{$brand->name|escape}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn_small btn_blue">{$btr->sviat__orders_export__apply|escape}</button>
                        <button type="button" class="btn btn_small btn-blue-outline" id="fn_orders_brand_filter_reset">{$btr->sviat__orders_export__reset|escape}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var brandIdsValue = '{$smarty.get.brand_ids|escape:'javascript'}';
            var brandIds = brandIdsValue ? brandIdsValue.split(',').filter(Boolean) : [];
            var select = document.getElementById('fn_orders_brand_select');
            var hiddenInput = document.getElementById('fn_orders_brand_ids_value');
            var form = document.getElementById('fn_orders_brand_filter_form');
            var resetButton = document.getElementById('fn_orders_brand_filter_reset');
            var modal = document.getElementById('fn_orders_brand_filter_modal');

            if (modal && modal.parentNode !== document.body) {
                document.body.appendChild(modal);
            }

            if (select && window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.selectpicker === 'function') {
                var $select = window.jQuery(select);
                if (!$select.data('selectpicker')) {
                    $select.selectpicker();
                }
                $select.selectpicker('val', brandIds);
                $select.selectpicker('refresh');
            } else if (select) {
                Array.from(select.options).forEach(function (option) {
                    option.selected = brandIds.indexOf(option.value) !== -1;
                });
            }

            function getSelectedBrandIds() {
                if (!select) {
                    return [];
                }
                if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.selectpicker === 'function') {
                    var $select = window.jQuery(select);
                    return ($select.val() || []).filter(Boolean);
                }
                return Array.from(select.selectedOptions || []).map(function (option) {
                    return option.value;
                }).filter(Boolean);
            }

            function syncHiddenInput() {
                if (hiddenInput) {
                    hiddenInput.value = getSelectedBrandIds().join(',');
                }
            }

            function appendBrandIdsToUrl(url) {
                if (!url) {
                    return url;
                }

                var parsedUrl = new URL(url, window.location.origin);
                parsedUrl.searchParams.delete('brand_ids');
                var selectedBrandIds = hiddenInput && hiddenInput.value ? hiddenInput.value.split(',').filter(Boolean) : [];
                if (selectedBrandIds.length) {
                    parsedUrl.searchParams.set('brand_ids', selectedBrandIds.join(','));
                }

                return parsedUrl.pathname + parsedUrl.search + parsedUrl.hash;
            }

            function applyFilter(event) {
                if (event) {
                    event.preventDefault();
                }

                syncHiddenInput();

                if (!form) {
                    return;
                }

                var params = new URLSearchParams();
                Array.from(form.elements).forEach(function (element) {
                    if (!element.name || element.disabled) {
                        return;
                    }

                    if ((element.type === 'checkbox' || element.type === 'radio') && !element.checked) {
                        return;
                    }

                    if (element.name === 'brand_ids' && !element.value) {
                        return;
                    }

                    params.set(element.name, element.value);
                });

                var action = form.getAttribute('action');
                if (action) {
                    var url = new URL(action, window.location.origin);
                    url.search = params.toString();
                    window.location.href = url.pathname + '?' + url.searchParams.toString();
                    return;
                }

                window.location.href = window.location.pathname + '?' + params.toString();
            }

            if (form) {
                form.addEventListener('submit', applyFilter);
            }

            if (select) {
                select.addEventListener('change', syncHiddenInput);
            }
            if (select && window.jQuery && typeof window.jQuery(select).on === 'function') {
                window.jQuery(select).on('changed.bs.select', syncHiddenInput);
            }

            if (resetButton && select && hiddenInput) {
                resetButton.addEventListener('click', function () {
                    hiddenInput.value = '';
                    if (window.jQuery && typeof window.jQuery(select).selectpicker === 'function') {
                        window.jQuery(select).selectpicker('val', []);
                    } else {
                        Array.from(select.options).forEach(function (option) {
                            option.selected = false;
                        });
                    }
                });
            }

            syncHiddenInput();

            if (hiddenInput && hiddenInput.value) {
                document.querySelectorAll('.view_info_visited__status, select[onchange*="location = this.value"] option').forEach(function (element) {
                    if (element.tagName === 'A') {
                        element.href = appendBrandIdsToUrl(element.getAttribute('href'));
                    } else if (element.value) {
                        element.value = appendBrandIdsToUrl(element.value);
                    }
                });

                document.querySelectorAll('form.search, form.box_date_filter').forEach(function (currentForm) {
                    if (!currentForm.querySelector('input[name="brand_ids"]')) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'brand_ids';
                        input.value = hiddenInput.value;
                        currentForm.appendChild(input);
                    }
                });
            }
        })();
    </script>
{/if}
