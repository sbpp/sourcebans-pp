<div class="flex m:flex-fd:column">
    <div class="admin_tab layout_box padding">
        <button id="admin_tab_mobile" class="button button-light admin_tab_mobile responsive_hide:desktop">
            Navigation
        </button>

        <div id="admin-page-menu" class="admin_tab_ul">
            {foreach from=$tabs item="tab"}
                <button onclick="openTab(this, '{$tab.name}');">{$tab.name}</button>
            {/foreach}
            <a href="index.php?p=admin">Back</a>
        </div>

        <script type="text/javascript" src="themes/{$theme}/scripts/tab.js"></script>
    </div>