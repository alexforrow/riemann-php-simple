# riemann-php-simple

Send events to Riemann using array syntax, similar to clients for other languages.

Support for UDP and TCP.

http://riemann.io/quickstart.html

## Example

```php
require __DIR__ . '/vendor/autoload.php';

$riemann = new Riemann\Client();

$riemann->send(array(
  'service' => 'test1',
  'state' => 'ok',
  'tags' => array('gauge', 'first'),
  'description' => 'first test',
  'ttl' => 60,
  'metric' => mt_rand(0, 99),
));
```

query the events:
```ruby
$ irb -r riemann/client
ruby-1.9.3 :001 > r = Riemann::Client.new
 => #<Riemann::Client ... >
ruby-1.9.3 :003 > r['service =~ "php%"']
```

## Acknowledgements

https://github.com/schnipseljagd/riemann-php-client
