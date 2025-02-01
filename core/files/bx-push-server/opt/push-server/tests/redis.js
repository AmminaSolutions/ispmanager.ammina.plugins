var redis = require("redis");
var client = redis.createClient();
var $ = require("../lib/util");

var channels = 1;
var elements = 60000;

for (var i = 0; i < channels; i++)
{
	var key = "channel:" + $.addLeftPad(i.toString(), 16, "0");
	for (var j = 0; j < elements; j++)
	{
		client.zadd(key, 0, "1423938115" + $.addLeftPad(j.toString(), 16, "0"));
	}

}

//client.unref();