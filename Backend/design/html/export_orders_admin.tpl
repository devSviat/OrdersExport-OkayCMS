{$meta_title=$btr->sviat__orders_export__settings_title scope=global}

<div class="main_header">
    <div class="main_header__item">
        <div class="main_header__inner">
            <div class="box_heading heading_page">{$btr->sviat__orders_export__settings_title|escape}</div>
        </div>
    </div>
    <div class="main_header__item">
        <div class="main_header__inner">
            <a href="{$root_url}/backend/index.php?controller=Sviat.OrdersExport.OrdersExportRunAdmin"
                class="btn btn_small btn_blue">{$btr->sviat__orders_export__to_export|escape}</a>
        </div>
    </div>
</div>

{if $message_success}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--success">
                <div class="alert__content">
                    <div class="alert__title">
                        {$btr->sviat__orders_export__saved|escape}
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}

<form method="post" class="fn_form_list">
    <input type="hidden" name="session_id" value="{$smarty.session.id}">

    <div class="boxed fn_toggle_wrap">
        <div class="heading_box">{$btr->sviat__orders_export__main_settings|escape}</div>
        <div class="toggle_body_wrap on fn_card">
            <div class="row">
                <div class="col-lg-3 col-md-6 col-sm-12 mb-h">
                    <div class="heading_label">
                        <span>{$btr->sviat__orders_export__orders_count|escape}</span>
                        <i class="fn_tooltips" title="{$btr->sviat__orders_export__orders_count_hint|escape}">
                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                        </i>
                    </div>
                    <input class="form-control" type="number" min="20" max="1000" step="10" name="orders_count" value="{$orders_export_orders_count|escape}">
                </div>

                <div class="col-lg-3 col-md-6 col-sm-12 mb-h">
                    <div class="heading_label">{$btr->sviat__orders_export__delimiter|escape}</div>
                    <select class="selectpicker form-control" name="column_delimiter">
                        <option value=";" {if $orders_export_column_delimiter == ';'}selected{/if}>{$btr->sviat__orders_export__delimiter_semicolon|escape}</option>
                        <option value="," {if $orders_export_column_delimiter == ','}selected{/if}>{$btr->sviat__orders_export__delimiter_comma|escape}</option>
                        <option value="tab" {if $orders_export_column_delimiter == "	"}selected{/if}>{$btr->sviat__orders_export__delimiter_tab|escape}</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6 col-sm-12 mb-h">
                    <div class="heading_label">{$btr->sviat__orders_export__default_ttn_mode|escape}</div>
                    <select class="selectpicker form-control" name="default_export_ttn">
                        <option value="0" {if $orders_export_default_export_ttn == '0'}selected{/if}>{$btr->sviat__orders_export__ttn_off|escape}</option>
                        <option value="1" {if $orders_export_default_export_ttn == '1'}selected{/if}>{$btr->sviat__orders_export__ttn_add|escape}</option>
                        <option value="2" {if $orders_export_default_export_ttn == '2'}selected{/if}>{$btr->sviat__orders_export__ttn_only|escape}</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6 col-sm-12 mb-h">
                    <div class="heading_label">
                        <span>{$btr->sviat__orders_export__orders_brand_filter|escape}</span>
                        <i class="fn_tooltips" title="{$btr->sviat__orders_export__orders_brand_filter_hint|escape}">
                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                        </i>
                    </div>
                    <label class="switch switch-default">
                        <input class="switch-input" name="show_orders_brand_filter" value="1" type="checkbox" {if $orders_export_show_orders_brand_filter}checked{/if}/>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="boxed fn_toggle_wrap">
        <div class="heading_box">{$btr->sviat__orders_export__instruction_title|escape}</div>
        <div class="toggle_body_wrap on fn_card">
            <div class="mb-h">
                {$btr->sviat__orders_export__instruction_step_1|escape}
            </div>
            <div class="mb-h">
                {$btr->sviat__orders_export__instruction_step_2|escape}
            </div>
            <div class="mb-h">
                {$btr->sviat__orders_export__instruction_step_3|escape}
            </div>
            <div class="mb-h">
                {$btr->sviat__orders_export__instruction_step_4|escape}
            </div>
            <div class="text_12 text_grey">
                {$btr->sviat__orders_export__instruction_brands_note|escape}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 col-md-12">
            <button type="submit" class="btn btn_small btn_blue">
                <span>{$btr->sviat__orders_export__save|escape}</span>
            </button>
        </div>
    </div>
</form>
