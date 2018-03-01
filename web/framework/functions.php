<?php

Flight::map('getQuote', function () {
    $quotes = [
        ["Buy a new PC!", "Viper"],
        ["I'm not lazy! I just utilize technical resources!", "Brizad"],
        ["I need to mow the lawn", "sslice"],
        ["Like A Glove!", 'Viper'],
        ["You're a Noob and You Know It!", "Viper"],
        ["Get your ass ingame", "Viper"],
        ["Mother F***ing Peices of Sh**", "Viper"],
        ["Shut up Bam", "[Everyone]"],
        ["Hi OllyBunch", "Viper"],
        ["Procrastination is like masturbation. Sure it feels good, but in the end you're only F***ing yourself!", "[Unknown]"],
        ["Rave's momma so fat she sat on the beach and Greenpeace threw her in", "SteamFriend"],
        ["Im just getting a beer", "Faith"],
        ["To be honest... , I DONT CARE!", "Viper"],
        ["Yams", "teame06"],
        ["built in cheat 1.6 - my friend told me theres a cheat where u can buy a car door and run around and it makes u invincible....", "gdogg"],
        ["i just join conversation when i see a chance to tell people they might be wrong, then i quickly leave, LIKE A BAT", "BAILOPAN"],
        ["Lets just blame it on FlyingMongoose", "[Everyone]"],
        ["Don't step on that *boom*... mine...", "Recon"],
        ["Looks through sniper scope... Sit ;)", "Recon"],
        ["That plugin looks like something you found in a junk yard.", "Recon"],
        ["That's exactly what I asked you not to do.", "Recon"],
        ["Why are you wasting your time looking at this?", "Recon"],
        ["You must have better things to do with your time", "Recon"],
        ["I pity da fool", "Mr. T"],
        ["you grew a 3rd head?", "Tsunami"],
        ["I dont think you want to know...", "devicenull"],
        ["Sheep sex isn't baaaaaa...aad", "Brizad"],
        ["Oh wow, he's got skillz spelled with an 's'", "Brizad"],
        ["I'll get to it this weekend... I promise", "Brizad"],
        ["People do crazy things all the time... Like eat a Arby's", "Marge Simpson"],
        ["I wish my lawn was emo, so it would cut itself", "SirTiger"],
        ["Oh no! I've overflowed my balls!", "Olly"]
    ];

    $num = rand(0, sizeof($quotes) - 1);
    return ['author' => $quotes[$num][1], 'quote' => $quotes[$num][0]];
});
