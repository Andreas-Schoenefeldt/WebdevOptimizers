/* Write this code to a user-extensions.js file and integrate into Selenium IDE. */

/** Workaround / solution for problem that Selenium throws Exception while 
  * waiting for page to load with error message like:
  * isNewPageLoaded found an old pageLoadError: 
  * "Permission denied for <http://www.facebook.com> to get property Location.href"
  * or "<http://www.facebook.com> wurde die Erlaubnis für das Lesen der Eigenschaft Location.href verweigert".
  *
  * Usage: Use command waitForMyPageToLoad instead of waitForPageToLoad as test case step's command with your timeout value as parameter.
  */
Selenium.prototype.doWaitForMyPageToLoad = function(timeout) {
	if (window["proxyInjectionMode"] == null || !window["proxyInjectionMode"]) {
		if (timeout == null) {
			timeout = this.defaultTimeout;
		}
		return Selenium.decorateFunctionWithTimeout(fnBind(this._isMyNewPageLoaded, this), timeout);
	}
};

/** New function to check whether page is loaded.
  * If isNewPageLoaded() throws exception then check whether it is the "old pageLoadError" / 
  * "facebook" exception, and if so, log a warning and go on. If not, throw exception.
  */
Selenium.prototype._isMyNewPageLoaded = function() {
	var r = false;
	try {
		r = this.browserbot.isNewPageLoaded();
	} catch (e) {
		LOG.warn(e.message);
		if (!( e.message.match(/isNewPageLoaded found an old pageLoadError/) || e.message.match(/http:..www.facebook.com/) )) {
			throw e;
		}
	}
	return r;
};