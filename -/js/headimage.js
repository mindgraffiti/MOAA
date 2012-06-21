<!--


var theImages = new Array()
// To add more image files, continue with the pattern below, adding to the array. Rememeber to increment the theImages[x] index!

theImages[0] = '/-/images/design/header1.jpg'
theImages[1] = '/-/images/design/header1.jpg'


// ======================================
// do not change anything below this line
// ======================================

var j = 0
var p = theImages.length;

var preBuffer = new Array()
for (i = 0; i < p; i++){
   preBuffer[i] = new Image()
   preBuffer[i].src = theImages[i]
}

var whichImage = Math.round(Math.random()*(p-1));
function showImage(){
document.write('<img src="'+theImages[whichImage]+'">');
}
//-->