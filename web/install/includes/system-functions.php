<?php
function HelpIcon($title, $text)
{
    return '<img border="0" align="absbottom" src="images/admin/help.png" class="tip" title="' .  $title . ' :: ' .  $text . '">';
}

function CreateQuote()
{
    $quotes = json_decode(file_get_contents('../configs/quotes.json'), true);
    $num = rand(0, count($quotes) - 1);
    return sprintf('"%s" - <i>%s</i>', $quotes[$num]['quote'], $quotes[$num]['author']);
}
