const superagent = require("superagent");


superagent
	.post("http://util.1c-bitrix.ru.smn/verify.php")
	//.send("BX_HASH=f9f8c89d17d035de75e7bd5bc12b5cb5&BX_LICENCE=7796339701d176f619d8b61bafc521cb&BX_TYPE=CP")
	// .send("BX_HASH=e865391991cc78aaaeb500340fe65168&BX_LICENCE=69bd7be5aee1e391f022d4d1da5b71fe&BX_TYPE=CP")
	.send("BX_HASH=0da651c6007c803893cc3622f4565a1c&BX_LICENCE=fd818684484258a5c6f0442a070661d6&BX_TYPE=CP")
	.retry(2)
	.timeout({
		response: 5000,
		deadline: 10000,
	})
	.end((err, res) => {
		console.log(res.body);
		console.log(res.status);
	}
);