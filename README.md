twitch-rss
==========

- [Website][1]

- Repositories
  - [GitHub][2]
  - [GitLab][3]


About
-----

Returns an rss feed of the past broadcasts of the selected channel


Usage
-----

- GET options
    - channel  
      name of the twitch channel
    - limit  
      max number of rss items to show
    - key  
      restricts the use of the script  
      must match the content of `config/key.txt`  
      you have to create the file "key.txt" yourself to enable this feature  
      use a password of your choice  
      ex: `?channel=gogcom&limit=10&key=topSecret`

- Client ID  
  put the client ID of the registered app in `config/clientid.txt`  
  see [Client-ID required for Kraken API calls][4]


[1]: http://spenibus.net
[2]: https://github.com/spenibus/twitch-rss-php
[3]: https://gitlab.com/spenibus/twitch-rss-php
[4]: https://blog.twitch.tv/client-id-required-for-kraken-api-calls-afbb8e95f843