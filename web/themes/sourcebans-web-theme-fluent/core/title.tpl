<div id="dialog-placement" class="popup popup_block">
    <div class="layout_box">
        <div class="popup_title padding:half">
            <div id="dialog-title"></div>
        </div>

        <div class="padding:half">
            <div class="popup_icon margin-bottom:half">
                <i id="dialog-icon"></i>
            </div>

            <div id="dialog-content-text"></div>
        </div>

        <div class="popup_footer padding:half flex" id="dialog-control"></div>
    </div>
</div>
<div id="dialog-placement-background" class="popup popup_background" onclick="closeMsg('');"></div>

<div class="page_header">
    <h1>{$title}</h1>
</div>

<div class="breadcrumb">
    {foreach from=$breadcrumb item="crumb"}
        <i class="fas fa-angle-right"></i> <a href="{$crumb.url}">{$crumb.title}</a>
    {/foreach}
</div>

<!-- <div id="content"> -->