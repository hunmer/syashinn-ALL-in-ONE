
// 收藏模块
var g_v_favorites = local_readJson('favorites', {});
function favorite(dom=null, json=null, b=null) {
    if (json === null)
        json = g_v_viewing.data;
    var key = "id-" + json.id;

    if (g_v_favorites[key] == undefined) {
        if (b === null)
            b = true;
    } else {
        if (b === null)
            b = false;
    }
    if (b) {
        g_v_favorites[key] = json;
    } else {
        delete (g_v_favorites[key]);
    }
    if (dom !== null) {
        dom.html(b ? 'favorite' : 'favorite_border');
    }
    local_saveJson('favorites', g_v_favorites);
    return b;
}

// 预载图像
function preloadImage(key, src) {
    return new Promise(function(resolve, reject) {
        let img = new Image();
        img.onload = function() {
            resolve(img);
            //加载时执行resolve函数
        }
        img.onerror = function() {
            reject(src + '这个地址错误');
            //抛出异常时执行reject函数
        }
        img.src = src;
    }
    );
}

// 下载
function download(url='') {
    if (url == '')
        url = $('#viewer img').attr('src');
    fetch(url).then(res=>res.blob().then(blob=>{
        var a = document.createElement('a');
        var url = window.URL.createObjectURL(blob);
        a.href = url;
        a.download = g_v_viewing.data.id + '-' + g_v_viewing.model + '-' + g_v_viewing.offset + '.jpg';
        a.click();
        window.URL.revokeObjectURL(url);
    }
    ))
}