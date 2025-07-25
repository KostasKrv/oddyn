# The Odds API Code Samples - PHP

The Odds API provides live odds for loads of sports from bookmakers around the world, in an easy to use JSON format.

Before getting started, be sure to get a free API key from [https://the-odds-api.com](https://the-odds-api.com)

For more info on the API, [see the docs](https://the-odds-api.com/liveapi/guides/v4/)


## Get Started

```
php sample-v4.php YOUR_API_KEY
```

This will print:
- A list of in-season sports
- Events and odds for the next 8 upcoming games (across all sports)
- Requests used & remaining for your api key

To change the sport, region and market, see the parameters specified at the beginning of sample-v4.php

Make sure the guzzle is installed in order to make http requests `composer require guzzlehttp/guzzle`


---


## Using Docker (Mac and Linux)

Build the image

```
docker build -t theoddsapi/sample:latest .
```

Run the php script in the container

```
docker run -t -i --rm -v "$(pwd)":/usr/src/app/ theoddsapi/sample:latest php sample-v4.php YOUR_API_KEY
```

## Community Contributions & 3rd Party Repos

This section showcases 3rd party PHP tools that integrate with The Odds API.

- [Odds API Wrapper](https://github.com/SethSharp/odds-api): A convenient API wrapper for the Odds API, designed for Composer environments such as Laravel.  


---

Want to see your PHP project featured here? [Open an issue](https://github.com/the-odds-api/samples-php/issues) in this repository to provide details of your project.

---

**⚠️ Disclaimer:** Third-party repositories are maintained by the community and are not officially supported by The Odds API. We bear no responsibility for third-party tools. While we appreciate the effort from our developer community, we cannot guarantee the quality, security, or compatibility of external code. Please review and test any third-party code thoroughly before using it in production environments.
