function checkMandatory(form){

		var valid = true;
		
		for(var i = 0; i < form.elements.length; i++){
			var element = form.elements[i];
			
			if (hasClass(element, 'mandatory')){
				var label = getChildNode(getFirstParent(element, 'tr'), 'th');
				if(getValue(element)) {
					removeClass(label, 'requiredError');
				} else {
					valid = false;
					addClass(label, 'requiredError');
				}
			}
			
		}
		
		return valid;
}
	
function getChildNode(element, tagname) {
	for (var i = 0; i < element.childNodes.length; i++) {
		var node = element.childNodes[i];
		// only care for element nodes
		if (node.nodeType == 1 && node.tagName.toLowerCase() == tagname.toLowerCase()){
			return node;
		}
	}
	
	return null;
}

function getFirstParent(element, tagname){
	while (element.parentNode != null) {
		if (element.parentNode.tagName.toLowerCase() == tagname.toLowerCase()) {
			return element.parentNode
		}
		
		element = element.parentNode;
	}
	
	return null;
}

function hasClass(element, className){
	var classes = element.className.split(' ');
	
	for (var i = 0; i < classes.length; i++){
		if (classes[i] == className) return true;
	}
	
	return false;
}

function addClass(element, className) {
	if (! hasClass(element, className)) {
		element.className = element.className + ' ' + className;
	}
}

function removeClass(element, className) {
	var classes = element.className.split(' ');
	element.className = ''
	for (var i = 0; i < classes.length; i++){
		if (classes[i] != className) element.className = element.className + ' ' + classes[i];
	}
}

function getValue(element) {
	var value = null;
	
	switch (element.type){
		case 'text':
			value = element.value;
			break;
		case 'select':
		case 'select-one':
			value = element.options[element.selectedIndex].value
			break;
			
	}
	
	
	return value;
}

function debug() {
	if (typeof(console) != 'undefined') {
		switch (arguments.length){
			case 1:
				console.log(arguments[0]);
				break;
			case 2:
				console.log(arguments[0], arguments[1]);
				break;
			case 3:
				console.log(arguments[0], arguments[1], arguments[2]);
				break;
			case 4:
				console.log(arguments[0], arguments[1], arguments[2], arguments[3]);
				break;
		}
	}
}

function trace() {
	if (typeof(console) != 'undefined') {
		console.trace();
	}
}