# Puff DI

`puff/di` 是轻量、PSR-11 兼容、Fiber scope 感知的依赖注入容器，不依赖 Illuminate。

```php
$container = new Puff\Di\Container();
$container->singleton(Database::class);
$service = $container->make(UserService::class);
```

支持自动构造注入、单例、别名、闭包工厂、方法调用注入、解析回调、数组访问、Facade 和 Fiber 隔离实例。
