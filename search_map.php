<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>節能交通搜尋測試</title>
    <style>
        /* 設定全螢幕 */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: "微軟正黑體", Arial, sans-serif;
        }

        /* 搜尋控制面板的樣式 (漂浮在地圖左上角) */
        #controls {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 5;
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            width: 300px;
        }

        input {
            width: 95%;
            margin-bottom: 10px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #28a745; /* 綠色代表節能 */
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 3px;
            font-size: 16px;
        }

        button:hover {
            background-color: #218838;
        }

        /* 地圖區域 */
        #map {
            height: 100%;
            width: 100%;
        }
    </style>
</head>
<body>

    <!-- 1. 搜尋輸入框面板 -->
    <div id="controls">
        <h3>🌱 節能路徑搜尋</h3>
        <!-- 輸入框 -->
        <input type="text" id="origin-input" placeholder="請輸入起點 (例如: 台北車站)">
        <input type="text" id="dest-input" placeholder="請輸入終點 (例如: 台北101)">
        
        <!-- 按鈕 -->
        <button id="search-btn">開始規劃路線</button>
        
        <!-- 顯示簡單的結果文字 -->
        <div id="result-text" style="margin-top:10px; color:#555; font-size:14px;"></div>
    </div>

    <!-- 2. 地圖容器 -->
    <div id="map"></div>

    <!-- 3. Google Maps API 腳本 -->
    <!-- 把 YOUR_API_KEY 換成你的金鑰 -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAFy0ChBy24ECuNAzspWl9-sYJ4Cp_J48g&callback=initMap" async defer></script>

    <script>
        // 全域變數
        let map;
        let directionsService;
        let directionsRenderer;

        function initMap() {
            // A. 初始化地圖
            map = new google.maps.Map(document.getElementById("map"), {
                center: { lat: 25.0478, lng: 121.5170 }, // 預設台北車站
                zoom: 14,
            });

            // B. 初始化路徑服務 (這就是負責問路的)
            directionsService = new google.maps.DirectionsService();

            // C. 初始化路徑渲染器 (這就是負責畫線的)
            directionsRenderer = new google.maps.DirectionsRenderer();
            directionsRenderer.setMap(map); // 告訴渲染器要把線畫在哪張地圖上

            // D. 綁定按鈕點擊事件
            document.getElementById("search-btn").addEventListener("click", calculateRoute);
        }

        function calculateRoute() {
            // 1. 取得使用者輸入
            const origin = document.getElementById("origin-input").value;
            const destination = document.getElementById("dest-input").value;

            if (!origin || !destination) {
                alert("拜託輸入起點和終點啦！");
                return;
            }

            // 2. 建立請求物件 (這是給 Google 看的訂單)
            const request = {
                origin: origin,
                destination: destination,
                travelMode: google.maps.TravelMode.TRANSIT, // 重點：設定為「大眾運輸」
                provideRouteAlternatives: true // 允許 Google 回傳多條路線讓我們選
            };

            // 3. 發送請求
            directionsService.route(request, (result, status) => {
                if (status === "OK") {
                    // 成功！把路線畫出來
                    directionsRenderer.setDirections(result);
                    
                    // 顯示一點資訊確認有抓到
                    const route = result.routes[0].legs[0];
                    document.getElementById("result-text").innerHTML = 
                        `<b>距離：</b> ${route.distance.text} <br>` +
                        `<b>時間：</b> ${route.duration.text}`;
                    
                    // 在 Console 印出詳細資料 (讓你按 F12 檢查)
                    console.log("Google 回傳的大眾運輸資料：", result);

                } else {
                    // 失敗
                    alert("找不到路線，原因：" + status);
                    // 常見原因：ZERO_RESULTS (太近或太遠沒車搭), NOT_FOUND (地名打錯)
                }
            });
        }
    </script>
</body>
</html>