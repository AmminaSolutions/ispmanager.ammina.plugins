/**
 *
 * @returns {Router}
 * @constructor
 */
function Router()
{
	if (!(this instanceof Router))
	{
		return new Router();
	}

	this.routes = {};
}

module.exports = Router;

Router.prototype.add = function(method, path, fn)
{
	method = method.toUpperCase();

	if (typeof(fn) !== "function")
	{
		throw new Error("Callback is not a function");
	}

	const route = {
		method: method,
		path: path,
		fn: fn
	};

	if (!this.isPathUnique(route))
	{
		throw new Error("Path "+ path + " already exists.");
	}

	if (!this.routes[method])
	{
		this.routes[method] = [];
	}

	this.routes[method].push(route);
};

Router.prototype.isPathUnique = function(route)
{
	if (!this.routes[route.method])
	{
		return true;
	}

	return !this.routes[route.method].some(function(item) {
		return item.path.toString() === route.path.toString();
	});
};

Router.prototype.match = function(method, path)
{
	method = method.toUpperCase();
	if (!this.routes[method])
	{
		return null;
	}

	for (let i = 0, len = this.routes[method].length; i < len; i++)
	{
		if (path.indexOf(this.routes[method][i].path) === 0)
		{
			return this.routes[method][i];
		}
	}

	return null;
};

Router.prototype.process = function(request, response)
{
	const route = this.match(request.method, request.url);
	if (route)
	{
		route.fn(request, response);
	}

	return route;
};

Router.prototype.get = function(path, fn)
{
	this.add("GET", path, fn);
};

Router.prototype.post = function(path, fn)
{
	this.add("POST", path, fn);
};

Router.prototype.delete = function(path, fn)
{
	this.add("DELETE", path, fn);
};

Router.prototype.options = function(path, fn)
{
	this.add("OPTIONS", path, fn);
};