
try{if('undefined'==typeof xajax)
throw{name:'SequenceError',message:'Error: xajax core was not detected, verbose module disabled.'}
if('undefined'==typeof xajax.debug)
throw{name:'SequenceError',message:'Error: xajax debugger was not detected, verbose module disabled.'}
xajax.debug.verbose={}
xajax.debug.verbose.expandObject=function(obj){var rec=true;if(1 < arguments.length)
rec=arguments[1];if('function'==typeof(obj)){return '[Function]';}else if('object'==typeof(obj)){if(true==rec){var t=' { ';var separator='';for(var m in obj){t+=separator;t+=m;t+=': ';try{t+=xajax.debug.verbose.expandObject(obj[m],false);}catch(e){t+='[n/a]';}
separator=', ';}
t+=' } ';return t;}else return '[Object]';}else return '"'+obj+'"';}
xajax.debug.verbose.makeFunction=function(obj,name){return function(){var fun=name;fun+='(';var separator='';var pLen=arguments.length;for(var p=0;p < pLen;++p){fun+=separator;fun+=xajax.debug.verbose.expandObject(arguments[p]);separator=',';}
fun+=');';var msg='--> ';msg+=fun;xajax.debug.writeMessage(msg);var returnValue=true;var code='returnValue = obj(';separator='';for(var p=0;p < pLen;++p){code+=separator;code+='arguments['+p+']';separator=',';}
code+=');';eval(code);msg='<-- ';msg+=fun;msg+=' returns ';msg+=xajax.debug.verbose.expandObject(returnValue);xajax.debug.writeMessage(msg);return returnValue;}
}
xajax.debug.verbose.hook=function(x,base){for(var m in x){if('function'==typeof(x[m])){x[m]=xajax.debug.verbose.makeFunction(x[m],base+m);}
}
}
xajax.debug.verbose.hook(xajax,'xajax.');xajax.debug.verbose.hook(xajax.callback,'xajax.callback.');xajax.debug.verbose.hook(xajax.css,'xajax.css.');xajax.debug.verbose.hook(xajax.dom,'xajax.dom.');xajax.debug.verbose.hook(xajax.events,'xajax.events.');xajax.debug.verbose.hook(xajax.forms,'xajax.forms.');xajax.debug.verbose.hook(xajax.js,'xajax.js.');xajax.debug.verbose.hook(xajax.tools,'xajax.tools.');xajax.debug.verbose.hook(xajax.tools.queue,'xajax.tools.queue.');xajax.debug.verbose.hook(xajax.command,'xajax.command.');xajax.debug.verbose.hook(xajax.command.handler,'xajax.command.handler.');xajax.debug.verbose.isLoaded=true;}catch(e){alert(e.name+': '+e.message);}
