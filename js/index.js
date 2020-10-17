$(function() {
	$('[data-toggle="offcanvas"]').on('click', function () {
	    $('.offcanvas-collapse').toggleClass('open')
	  })	

	window.history.pushState(null, null, "#");
	window.addEventListener("popstate", function(event) {
		window.history.pushState(null, null, "#");
		event.preventDefault(true);
		event.stopPropagation();
		//$('#modal1').modal('close');
	});
	$(window).scroll(function() {
	    var scrollTop = $(this).scrollTop();
	    var i = $(document).height() - (scrollTop + $(this).height());
	    if (i <= 50) {
	        //滚动条到达底部
			var now = new Date().getTime() / 1000;
            if (g_b_loading || now - g_i_loading_last <= 3) return;
	        

	        var dom = $('._active');
	        switch(dom.attr('id')){
	        	case '_ui-sort':
	        		http_sortList(g_v_showing_sort.id, g_v_showing_sort.sid, ++g_v_showing_sort.page, g_v_showing_sort.url != undefined ? g_v_showing_sort.url : '');
	        		break;

	        	default:
	        		return;
	        }
			g_i_loading_last = now;
	    } else if (scrollTop <= 0) {//滚动条到达顶部
	    }
	});
	setInterval(function(){
	   // 取屏幕中间元素
		var d = $(document.elementFromPoint($(this).width() / 2, $(this).height() / 2)).parents('.-album');
		var sid = d.attr('data-sid');
		if(sid === undefined) return;
		if(d.attr('data-loaded') == undefined && d.attr('data-loading') != "true"){
			d.attr('data-loading', "true")
			http_sortList(d.attr('data-id'), sid, 1, true);
		}
	}, 500);
	// setTimeout(function(){
	// 	toNav(59);
	// }, 500);
	init();
});  


function toNav(id){
	var d = $('.nav-link[data-id='+id+']')[0];
	// $('.nav-underline').animate({
 //            scrollLeft: d.offsetLeft+'px'
 //        }, 1000);
	d.click();
}

var g_i_loading_last = 0; //  最后加载的时间
var g_b_loading = false; // 是否正在加载(分类加载除外)
var g_v_gallery; // viewer
var g_s_ui_last; // 最后浏览的ui
function showUI(id) {
	$('._active').removeClass('_active');
	if(id == undefined){
		id = '_con'+g_v_showing.id;
		setNavDisplay(true);
	}
    $('.-con').each(function(index, el) {
        if (el.id == id) {
            $(el).removeClass('hide').addClass('_active');
        } else {
            $(el).addClass('hide');
        }
    });
}

// ----------------------
// 流程
function init(){
	data_preload();
}

// ----------------------
// 网络请求
function data_preload(){
	setLoading(true);
	$.ajax({
		url: './php/web.json',
		dataType: 'json'
	})
	.done(function(json) {
		data_load('web', json);
	})
	.fail(function() {
	})
	.always(function() {
		setLoading(false);
	});
}

function data_load(type, json, ...rest){
	//console.log('data_load', type, json);
	switch(type){
		case 'web':
			data_load_web(json);
			break;

		case 'sort':
			data_load_sort(json, rest[0].album );
			break;

		case 'album':
			data_load_album(json);
			break;
	}
}

// 数据 - 加载 - 网站列表
function data_load_web(json){
	g_v_web = json;
	var d,
	h = '', h1 = '';
	for(var d of json){
		if(d.hide !== undefined || d.desc !== undefined) continue;
		h = h + ' <li class="nav-item"><a class="nav-link" data-id='+d.id+' href="javascript: view_site(' + d.id + ');">' + d.webname + '</a></li>';
		//h1 = h1 + '<div class="-con container-fluid" id="_con' + d.id + '"></div>';
	}
	$('.nav-tabs').append(h);
	//$('body').append(h1);
}

var g_a_cache_albums = []; // 缓存的相册 [url主键]

// 数据 - 加载 - 分类下的专辑列表
// b_album string->url
function data_load_sort(json, b_album){
	g_v_sort = json;
	var d, h = '', h1 = '', c = 0, sub = typeof(b_album) == 'string';
	console.log(json);
	if(json.res === undefined) return;

	for(var d of json.res){
		if(c % 3 === 0){
			h = h + '<div class="w-100"></div>';
		}
		g_a_cache_albums[d.url] = {
			cover: d.cover,
			title: d.title
		};
		h = h + `
			<div class="col -image-list" data-url="` + d.url + `">
				<img class="img-thumbnail" onclick="enter_album(` + json.id + `, '` + d.url + `', `+(sub ? 'true' : 'false')+`);" src="` + d.cover + `" alt='`+d.title+`'>
			
			</div>
		`;
		// 	<span>` + d.title + `</span>
		c++;
		if(!sub && b_album && c === 6) break;
	}
	var dom;
	if(!sub && b_album){ // 固定列表加载
		// addClass("pb-5").
		dom = $('#_con'+json.id+' .-album[data-id='+json.id+'][data-sid='+json.sid+']');
		dom.find('.-image-list').html(h);
	}else{
		dom = $('#_sort-list');
		dom.append(h);
	}
	dom.imagesLoaded()
		.progress( function( instance, image ) {
		   	if(!image.isLoaded){
		   		image.img.src = "images/404.png";
		   	}
	  });
}

var g_v_showing = {}; // 正在浏览
var g_s_urls = ""; // 相册的图片下载链接
// 数据 - 加载 - 相册图片
function data_load_album(json){
	var j = get_json('host', json.id);
	if(j === undefined) return;	

	g_s_urls = "";
	var d,
	h = '', h1 = '', c = 0;
	console.log(json);

	var images = json.cover;
	var parasm = get_url_params(j);
	for(var d of json.urls){
		images.push(g_s_api+'getPage.php?id='+json.id+'&url='+d+'&data='+parasm);
	}
	var max = images.length;
	if(max === 0){
		hsycms.error('error','NOT DATA!',function(){
			showUI(g_s_ui_last);
		},1800)
		return;
	}
	hsycms.tips('tips', max + 'p oaded!', function(){

	}, 1000);	
	for(var d of images){
		if(c % 3 === 0){
			h = h + '<div class="w-100"></div>';
		}		
		c++;
		h = h + `
			<div class="col -image-list">
				<img class="img-thumbnail" src="` + d + `" alt='`+c+'/'+max+`'>
			</div>
		`;
		g_s_urls = g_s_urls + (d.substr(0, 1) == '.' ? g_s_api + d.substr(1, d.strlen - 1) : d) + "\r\n";
	}
	$('#_photo-list').append(h).
	imagesLoaded()
		.progress( function( instance, image ) {
		   	if(!image.isLoaded){
		   		image.img.src = "images/404.png";
		   	}
	  });	
	g_v_gallery = new Viewer($('#_ui-album')[0]);
	g_v_gallery.show();
}
// --------------------


// -------------------
// 用户操作
var g_v_ = [];
var g_a_lables = []; // 分类

function view_site(id){
	$('.nav-link.active').removeClass('active').removeClass(' btn-outline-success').removeClass('white-text');

	$('.nav-link[data-id='+id+']').addClass('active').addClass(' btn-outline-success').addClass('white-text');
	var j = get_json('host', id);
	if(!j) return;
	showUI('_con' + id);

	// 初次点击
	if(g_a_lables[id] === undefined){
		// 动态插入容器
		var h = '<div class="-con container-fluid" id="_con' + id + '">';

		// 加载分类
		g_a_lables[id] = {};
		var lab_ids, lab_names, i = 0;
		while(j['label-'+i] !== undefined){
			lab_ids = j['labelid-'+i].split(';');
			lab_names = j['label-'+i].split(';');
			for(let i1=0;i1<lab_ids.length;i1++){
				if(lab_names[i1] !== undefined){
					g_a_lables[id][lab_ids[i1]] = lab_names[i1];
					h = h + `
					<div class="container -album pt-4 shadow bg-white rounded" data-id="`+id+`" data-sid="`+i1+`">
						<div class="-header align-middle">
							<div class="row">
								<div class="col">
									<span class="display-5">`+lab_names[i1]+`</span>
								</div>
								<div class="col-6 text-center">

								</div>
								<div class="col text-right">
									<button onclick="enter_sortList(`+id+`, `+i1+`, '`+lab_names[i1]+`');" type="button" class="btn btn-outline-info btn-sm">
										<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-arrow-right" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
</svg></button>
								</div>
							</div>
						</div>
					<div class="-image-list row pt-2 pb-2">`;
				for(let y=0;y<2;y++){
					for(let x=0;x<3;x++){
						h = h + `
							<div class="col">
								<img class="img-thumbnail" src="images/loading.gif">
							</div>							
						`;
					}
					h = h + '<div class="w-100 mb-4"></div>';
				}
				h = h + '</div></div>';
				}
			}
			i++;
		}
		$('body').append(h + '</div>');

	}else{

	}
}
var g_v_showing_sort; // 正在浏览的分类

// 分类 - 显示列表
function enter_sortList(id, sid, title){
	setNavDisplay(false);

	var dom = $('#_ui-sort');
	dom.find('.-title').html(title);
	if(dom.attr('data-id') == id && dom.attr('data-sid') == sid){
		// 上次打开过
		return showUI('_ui-sort');
	}
	dom.attr('data-id', id).attr('data-sid', sid).find('#_sort-list').html('');
	showUI('_ui-sort');

	g_v_showing_sort = {id: id, sid: sid, page: 1};
	http_sortList(id, sid, 1);
}

var g_a_cache_sort = {};
var g_v_ajax = {}; // ajax请求

// 分类 - 请求数据
function http_sortList(id, sid, page = 1, b_album = false, url = ''){
	// 已被缓存
	if(g_a_cache_sort[id+'-'+sid] !== undefined && g_a_cache_sort[id+'-'+sid][page] !== undefined){
		console.log('使用缓存 ' + page);
		return data_load('sort', g_a_cache_sort[id+'-'+sid][page], {album: b_album});
	}
	if(b_album === undefined) setLoading(true);
	$('#_ui-sort .-title').html('Loading page ' + page);

	if(g_v_ajax.sort != undefined) g_v_ajax.sort.abort();
	g_v_ajax.sort = $.ajax({
		url: g_s_api+'api.php?id=' + id + '&sid=' + sid + '&url='+url+'&page='+ page,
		dataType: 'json'
	})
	.done(function(json) {
		// 缓存
		getAblumDom(id, sid).attr('data-loaded', 1);

		if(g_a_cache_sort[id+'-'+sid] == undefined) g_a_cache_sort[id+'-'+sid] = {};
		g_a_cache_sort[id+'-'+sid][page] = json;
		data_load('sort', json, {album: b_album});
	})
	.fail(function() {
	})
	.always(function() {
		getAblumDom(id, sid).attr('data-loading', "false");
		$('#_ui-sort .-title').html('');
		setLoading(false);
	});
	
}

function getAblumDom(id, sid){
	return dom = $('#_con'+id+' .-album[data-id='+id+'][data-sid='+sid+']');
}


// 相册 - 打开相册
function enter_album(id, url, sub = false){
	console.log('enter_album');
	var j = get_json('host', id);
	if(!j) return;

	g_v_showing.id = id;
	g_v_showing.url = url;
	g_v_showing.json = j;

	setNavDisplay(false);
	var dom = $('#_photo-list');
	if(!sub && dom.attr('data-url') == url){
		// 上次打开过
		console.log('上次打开过');
		return showUI('_ui-album');
	}

	g_s_ui_last = '_ui-sort';
	dom.attr('data-url', url).html('');
	var info = g_a_cache_albums[url];
	$('#_ui-album .-title').html(info.title);
	setLoading(true);
	if(!sub) sub = j.children !== undefined ;
	var url = sub ? g_s_api+'api.php?id='+id+'&url=' + url+'&page=1' : g_s_api+'getPage.php?id='+id+'&url=' + url + '&img=0&data=' + get_url_params(j);
	console.log(url);

	$.ajax({
		url: url,
		dataType: 'json'
	})
	.done(function(json) {
		if(sub){
			console.log("sub");
			g_v_showing_sort = {id: id, url: url, page: 1}; // 定义url属性
			// 加载分类
			$('#_sort-list').html(''); // 偷懒清空...
			data_load('sort', json, {album: url});
			showUI('_ui-sort');
		}else{
			console.log("ss");
			// 加载相册
			data_load('album', json);
			showUI('_ui-album');
		}
	})
	.fail(function() {
		setNavDisplay(true);
	})
	.always(function() {
		setLoading(false);
	});		
}

// 请求参数
function get_url_params($value){
	return utf8_to_b64(JSON.stringify({
		'elephtotolist': _g($value, 'elephtotolist'),
		'eleimgsrc': _g($value, 'eleimgsrc'),
		'imgrule': _g($value, 'imgrule'),
		'once': $value['once'] != undefined,
		'charset': $value['charset'],
		'res': $value['res'] != undefined ? $value['res'] : []					
	}));
}

// ----------------
// 本地数据
var g_v_web; // 站点数据
function get_json(type, key){
	switch(type){
		case 'host':
			for(var d of g_v_web){
				if(d.id == key) return d;
			}
			break;
	}
}

// ----------------
// 杂

function setNavDisplay(b){
	if(b){
		$('.navbar, .nav-scroller').show();
	}else{
		$('.navbar, .nav-scroller').hide();
	}
}

function _g($j, $s){
	if($j['children'] != undefined && $j['children'][$s] != undefined){
		return $j['children'][$s];
	}
	return $j[$s];
}

function download(){
	$('.hscysm-model-title').html('images links : ');
	$('.hsycms-model-content').html('<textarea class="form-control" id="exampleFormControlTextarea1" rows="3">'+g_s_urls+'</textarea>');
	hsycms.alert('model');
}

function setLoading(b, title = 'cancel'){
     g_b_loading = b;
	if(b){
      hsycms.loading('loading',title);
  }else{
  	 hsycms.hideLoading('loading');
  }
}

function scrollImage(){
	var offset = $('.viewer-active').offset().top - $('.viewer-navbar').height();
	console.log(offset);
	if(offset > 0){
		$('.viewer-navbar').css('top', 0 - offset + 'px');
	}
}