# Puff DI

`puff/di`  light PSR-11 support php fiber scope discover inject container 

```php
$container = new Puff\Di\Container();
$container->singleton(Database::class);
$service = $container->make(UserService::class);
```
support autoload inject container single to course prase callback and with array access facade with fiber 