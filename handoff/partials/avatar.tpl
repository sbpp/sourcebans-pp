{* avatar.tpl — params: name, size (default 32) *}
{assign var=_size value=$size|default:32}
{assign var=_initials value=$name|truncate:2:'':true|upper}
{assign var=_hue value=$name|md5|substr:0:2|hexdec}
<span class="avatar" style="width:{$_size}px;height:{$_size}px;background:hsl({$_hue} 55% 45%);font-size:{($_size*0.36)|round}px">{$_initials}</span>
