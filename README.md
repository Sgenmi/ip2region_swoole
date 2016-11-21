# ip2region_swoole
ip2region Swoole版本,性能惊人

##ip2region是什么?

请见原作品:[Ip2region](https://github.com/lionsoul2014/ip2region)

##Swoole是什么?

请见原作品[Swoole](https://github.com/swoole/swoole-src)




* 采用Swoole改写了php/Ip2Region.class, 直接给用户提供服务
  
* IP库数据预加载,长住内存,提高性能
* 不停服务,平滑更新IP数据

##平滑重启

    http://127.0.0.1:7070/?ac=reload



##性能测试

```
ab -c 100 -n 100000 http://127.0.0.1:7070/?ip=125.118.66.241
```

![image](https://github.com/Sgenmi/ip2region_swoole/blob/develop/ab-server.jpg)