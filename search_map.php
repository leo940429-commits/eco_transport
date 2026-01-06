<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>節能交通搜尋 (多模式切換版)</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        /* 全域設定 */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Roboto', "微軟正黑體", sans-serif;
        }

        #map { height: 100%; width: 100%; }

        /* --- 搜尋面板 --- */
        #controls {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 5;
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            width: 340px; /* 稍微加寬一點 */
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
        }

        h3 { margin: 0 0 10px 0; color: #202124; font-size: 18px; }

        .input-wrapper { position: relative; margin-bottom: 10px; }

        input[type="text"] {
            width: 100%;
            padding: 10px 35px 10px 10px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
        }
        input[type="text"]:focus { border-color: #4285f4; box-shadow: 0 0 0 2px rgba(66,133,244,0.2); }

        .clear-btn {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            cursor: pointer; color: #70757a; font-size: 18px; display: none;
        }

        #search-btn {
            width: 100%; padding: 10px; background-color: #1a73e8; color: white;
            border: none; cursor: pointer; border-radius: 4px; font-size: 14px;
            font-weight: 500; transition: background 0.3s;
        }
        #search-btn:hover { background-color: #1557b0; }

        /* --- 交通工具選擇列 (預設隱藏) --- */
        #mode-selector {
            display: none; /* 一開始不顯示，搜尋後才出來 */
            margin-top: 15px;
            border-top: 1px solid #eee;
            padding-top: 10px;
            overflow-x: auto; /* 橫向捲動，以防按鈕太多 */
            white-space: nowrap; /* 讓按鈕排成一排 */
            padding-bottom: 5px; /* 預留捲動軸空間 */
        }
        
        /* 隱藏捲動軸但保留功能 (美觀) */
        #mode-selector::-webkit-scrollbar { height: 4px; }
        #mode-selector::-webkit-scrollbar-thumb { background: #ccc; border-radius: 2px; }

        /* 模式按鈕樣式 */
        .mode-btn {
            display: inline-block;
            padding: 6px 12px;
            margin-right: 5px;
            border: 1px solid #dadce0;
            border-radius: 20px; /* 膠囊狀 */
            background: white;
            color: #5f6368;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
        }

        .mode-btn:hover { background-color: #f1f3f4; }

        /* 被選中的按鈕樣式 (綠色代表節能) */
        .mode-btn.active {
            background-color: #e6f4ea;
            color: #137333;
            border-color: #137333;
            font-weight: bold;
        }

        /* 結果顯示區 */
        #result-text {
            margin-top: 10px; color: #3c4043; font-size: 14px; line-height: 1.6;
        }
    </style>
</head>
<body>

    <div id="controls">
        <h3>🌱 節能路徑規劃</h3>
        
        <div class="input-wrapper">
            <input type="text" id="origin-input" placeholder="起點" oninput="toggleClearBtn('origin-input', 'clear-origin')">
            <span id="clear-origin" class="clear-btn" onclick="clearInput('origin-input', 'clear-origin')">&times;</span>
        </div>

        <div class="input-wrapper">
            <input type="text" id="dest-input" placeholder="終點" oninput="toggleClearBtn('dest-input', 'clear-dest')">
            <span id="clear-dest" class="clear-btn" onclick="clearInput('dest-input', 'clear-dest')">&times;</span>
        </div>
        
        <button id="search-btn">🔍 查詢路線</button>

        <!-- 交通工具選擇按鈕 (搜尋後出現) -->
        <div id="mode-selector">
            <!-- data-mode: Google主要模式, data-transit: 細分的大眾運輸類型 -->
            <button class="mode-btn active" onclick="changeMode(this, 'TRANSIT', 'SUBWAY')">🚇 捷運</button>
            <button class="mode-btn" onclick="changeMode(this, 'TRANSIT', 'BUS')">🚌 公車</button>
            <button class="mode-btn" onclick="changeMode(this, 'TRANSIT', 'TRAIN')">🚆 鐵路/高鐵</button>
            <button class="mode-btn" onclick="changeMode(this, 'BICYCLING', '')">🚲 自行車</button>
            <button class="mode-btn" onclick="changeMode(this, 'WALKING', '')">🚶 走路</button>
            <button class="mode-btn" onclick="changeMode(this, 'TWO_WHEELER', '')">🛵 機車</button>
            <button class="mode-btn" onclick="changeMode(this, 'DRIVING', '')">🚗 開車</button>
        </div>
        
        <div id="result-text"></div>
    </div>

    <div id="map"></div>

    <!-- 請換成你的 API Key -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAFy0ChBy24ECuNAzspWl9-sYJ4Cp_J48g&callback=initMap" async defer></script>

    <script>
        let map;
        let directionsService;
        let directionsRenderer;
        
        // 預設模式：大眾運輸 + 捷運
        let currentTravelMode = 'TRANSIT';
        let currentTransitType = 'SUBWAY'; // 如果不是 TRANSIT 模式，這個變數會被忽略

        function initMap() {
            map = new google.maps.Map(document.getElementById("map"), {
                center: { lat: 25.0478, lng: 121.5170 },
                zoom: 14,
                mapTypeControl: false,
                fullscreenControl: false,
            });

            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer();
            directionsRenderer.setMap(map);

            document.getElementById("search-btn").addEventListener("click", function() {
                // 按下搜尋按鈕時，顯示下方的選項
                document.getElementById("mode-selector").style.display = "block";
                calculateRoute();
            });

            // 街景隱藏功能
            const streetView = map.getStreetView();
            const controlsDiv = document.getElementById("controls");
            google.maps.event.addListener(streetView, 'visible_changed', function() {
                controlsDiv.style.display = streetView.getVisible() ? "none" : "block";
            });
        }

        // 切換交通工具的函式
        function changeMode(btnElement, mode, transitType) {
            // 1. 更新全域變數
            currentTravelMode = mode;
            currentTransitType = transitType;

            // 2. 處理按鈕外觀 (把別人的 active 拿掉，加到自己身上)
            const buttons = document.querySelectorAll('.mode-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            btnElement.classList.add('active');

            // 3. 重新計算路線
            calculateRoute();
        }

        function calculateRoute() {
            const origin = document.getElementById("origin-input").value;
            const destination = document.getElementById("dest-input").value;

            if (!origin || !destination) return; // 如果沒輸入就不動作

            // 建立請求
            const request = {
                origin: origin,
                destination: destination,
                travelMode: google.maps.TravelMode[currentTravelMode], // 動態帶入模式
                provideRouteAlternatives: true
            };

            // 如果是大眾運輸模式，要加入 transitOptions 來篩選車種
            if (currentTravelMode === 'TRANSIT' && currentTransitType !== '') {
                request.transitOptions = {
                    modes: [google.maps.TransitMode[currentTransitType]],
                    routingPreference: 'FEWER_TRANSFERS'
                };
            }

            directionsService.route(request, (result, status) => {
                if (status === "OK") {
                    directionsRenderer.setDirections(result);
                    const route = result.routes[0].legs[0];
                    
                    // 根據模式顯示不同文字
                    let modeText = "";
                    if(currentTravelMode === 'TRANSIT') modeText = "(大眾運輸)";
                    else if(currentTravelMode === 'DRIVING') modeText = "(開車)";
                    else if(currentTravelMode === 'TWO_WHEELER') modeText = "(機車)";
                    
                    document.getElementById("result-text").innerHTML = 
                        `<strong>${modeText}</strong><br>` +
                        `距離：${route.distance.text} <br>` +
                        `預估時間：${route.duration.text}`;
                } else {
                    // 如果這種交通工具到不了 (例如過海不能走路)，要提示使用者
                    document.getElementById("result-text").innerHTML = 
                        `<span style="color:red">找不到此交通方式的路線 (status: ${status})</span>`;
                }
            });
        }

        // 清除與顯示 X 按鈕的小工具
        function clearInput(inputId, btnId) {
            const input = document.getElementById(inputId);
            input.value = "";
            input.focus();
            toggleClearBtn(inputId, btnId);
        }
        function toggleClearBtn(inputId, btnId) {
            const input = document.getElementById(inputId);
            const btn = document.getElementById(btnId);
            btn.style.display = input.value.length > 0 ? "block" : "none";
        }
    </script>
</body>
</html>