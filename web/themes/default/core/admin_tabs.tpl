<div id="admin-page-menu">
    {foreach from=$tabs item=tab}
        <a onclick="openTab(this, '{$tab.name}');">{$tab.name}</a>
    {/foreach}
    <a href="javascript:history.go(-1);">Back</a>
</div>
