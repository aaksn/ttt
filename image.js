function showImg(input,img) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();

            if(img==0)
			{
                reader.onload = function (e) {
                    $('#inputimg')
                        .attr('src', e.target.result)
                        .width(150)
                        .height(200);
                };
			}
            else
			{
                reader.onload = function (e) {
                    $('#outputimg')
                        .attr('src', e.target.result)
                        .width(150)
                        .height(200);
                };
			}


            reader.readAsDataURL(input.files[0]);
        }
    }
onload = function() {

	img = document.getElementsByTagName('img')
	img = {
		a: img[0],
		b: img[1]
	}
}
	
function getRealDisplay(elem) {
	if (elem.currentStyle) {
		return elem.currentStyle.display
	} else if (window.getComputedStyle) {
		var computedStyle = window.getComputedStyle(elem, null )

		return computedStyle.getPropertyValue('display')
	}
}

function hide(el) {
	if (!el.getAttribute('displayOld')) {
		el.setAttribute("displayOld", el.style.display)
	}

	el.style.display = "none"
}

displayCache = {}

function isHidden(el) {
	var width = el.offsetWidth, height = el.offsetHeight,
		tr = el.nodeName.toLowerCase() === "tr"

	return width === 0 && height === 0 && !tr ?
		true : width > 0 && height > 0 && !tr ? false :	getRealDisplay(el)
}
	
function show(el) {

	if (getRealDisplay(el) != 'none') return

	var old = el.getAttribute("displayOld");
	el.style.display = old || "";

	if ( getRealDisplay(el) === "none" ) {
		var nodeName = el.nodeName, body = document.body, display

		if ( displayCache[nodeName] ) {
			display = displayCache[nodeName]
		} else {
			var testElem = document.createElement(nodeName)
			body.appendChild(testElem)
			display = getRealDisplay(testElem)

			if (display === "none" ) {
				display = "block"
			}

			body.removeChild(testElem)
			displayCache[nodeName] = display
		}

		el.setAttribute('displayOld', display)
		el.style.display = display
	}
}