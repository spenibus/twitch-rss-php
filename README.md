twitch-rss
http://spenibus.net
https://github.com/spenibus/twitch-rss-php
https://gitlab.com/spenibus/twitch-rss-php


returns an rss feed of the past broadcasts of the channel

GET options
   channel
      name of the twitch channel
   limit
      max number of rss items to show
   key
      restricts the use of the script
      must match the content of "config/key.txt"
      you have to create the file "key.txt" yourself to enable this feature
      use a password of your choice
      ex: ?channel=gogcom&limit=10&key=topSecret