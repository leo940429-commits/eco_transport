<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>節能交通搜尋 (含詳細指引)</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        /* 全域設定 */
        html, body { height: 100%; margin: 0; padding: 0; font-family: 'Roboto', "微軟正黑體", sans-serif; }
        #map { height: 100%; width: 100%; }

        /* 左側控制面板容器 */
        #left-panel {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 5;
            width: 360px;
            max-height: 90vh; /* 避免超過螢幕高度 */
            display: flex;
            flex-direction: column;
            gap: 10px; /* 搜尋框跟路線面板的間距 */
        }

        /* 1. 搜尋框區塊 */
        #search-box {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* 2. 詳細路線面板 (預設隱藏) */
        #directions-panel {
            background-color: white;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            overflow-y: auto; /* 內容太長可捲動 */
            display: none; /* 搜尋後才顯示 */
        }

        /* 路線步驟樣式 */
        .step-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: flex-start;
        }
        .step-item:last-child { border-bottom: none; }
        
        .step-icon {
            font-size: 20px;
            margin-right: 15px;
            min-width: 30px;
            text-align: center;
        }
        
        .step-content { font-size: 14px; color: #333; }
        .step-instruction { font-weight: 500; margin-bottom: 4px; }
        .step-detail { font-size: 13px; color: #666; }
        .transit-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            color: white;
            font-size: 12px;
            font-weight: bold;
            margin-right: 5px;
        }

        /* 其他通用樣式 (跟之前一樣) */
        h3 { margin: 0 0 10px 0; font-size: 18px; }
        .input-wrapper { position: relative; margin-bottom: 10px; }
        input[type="text"] { width: 100%; padding: 10px 35px 10px 10px; border: 1px solid #dadce0; border-radius: 4px; box-sizing: border-box; outline: none; }
        input[type="text"]:focus { border-color: #4285f4; box-shadow: 0 0 0 2px rgba(66,133,244,0.2); }
        .clear-btn { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #70757a; display: none; }
        #search-btn { width: 100%; padding: 10px; background-color: #1a73e8; color: white; border: none; cursor: pointer; border-radius: 4px; }
        #search-btn:hover { background-color: #1557b0; }
        #mode-selector { display: none; margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px; overflow-x: auto; white-space: nowrap; padding-bottom: 5px; }
        .mode-btn { display: inline-block; padding: 6px 12px; margin-right: 5px; border: 1px solid #dadce0; border-radius: 20px; background: white; color: #5f6368; cursor: pointer; }
        .mode-btn.active { background-color: #e6f4ea; color: #137333; border-color: #137333; font-weight: bold; }
        #summary-text { margin-top: 10px; color: #3c4043; font-size: 14px; line-height: 1.6; }
    </style>
</head>
<body>

    <div id="left-panel">
        <!-- 搜尋區 -->
        <div id="search-box">
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

            <div id="mode-selector">
                <button class="mode-btn active" onclick="changeMode(this, 'TRANSIT', 'SUBWAY')">🚇 捷運</button>
                <button class="mode-btn" onclick="changeMode(this, 'TRANSIT', 'BUS')">🚌 公車</button>
                <button class="mode-btn" onclick="changeMode(this, 'TRANSIT', 'TRAIN')">🚆 鐵路</button>
                <button class="mode-btn" onclick="changeMode(this, 'BICYCLING', '')">🚲 自行車</button>
                <button class="mode-btn" onclick="changeMode(this, 'WALKING', '')">🚶 走路</button>
                <button class="mode-btn" onclick="changeMode(this, 'TWO_WHEELER', '')">🛵 機車</button>
            </div>
            
            <div id="summary-text"></div>
        </div>

        <!-- 詳細路線指引區 (新增的) -->
        <div id="directions-panel"></div>
    </div>

    <div id="map"></div>

    <!-- 請換成你的 API Key -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAFy0ChBy24ECuNAzspWl9-sYJ4Cp_J48g&callback=initMap" async defer></script>

    <script>
        let map, directionsService, directionsRenderer;
        let currentTravelMode = 'TRANSIT';
        let currentTransitType = 'SUBWAY';

        function initMap() {
            map = new google.maps.Map(document.getElementById("map"), {
                center: { lat: 25.0478, lng: 121.5170 },
                zoom: 14,
                mapTypeControl: false,
                fullscreenControl: false,
            });

            directionsService = new google.maps.DirectionsService();
            
            // 讓 Renderer 不要把預設的文字面板塞進地圖裡，我們要自己控制
            directionsRenderer = new google.maps.DirectionsRenderer({
                map: map,
                suppressMarkers: false // 預設顯示起終點圖標
            });

            document.getElementById("search-btn").addEventListener("click", function() {
                document.getElementById("mode-selector").style.display = "block";
                calculateRoute();
            });

            // 街景自動隱藏左側面板
            const streetView = map.getStreetView();
            const leftPanel = document.getElementById("left-panel");
            google.maps.event.addListener(streetView, 'visible_changed', function() {
                leftPanel.style.display = streetView.getVisible() ? "none" : "flex";
            });
        }

        function changeMode(btn, mode, type) {
            currentTravelMode = mode;
            currentTransitType = type;
            document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            calculateRoute();
        }

        function calculateRoute() {
            const origin = document.getElementById("origin-input").value;
            const destination = document.getElementById("dest-input").value;
            if (!origin || !destination) return;

            const request = {
                origin: origin,
                destination: destination,
                travelMode: google.maps.TravelMode[currentTravelMode],
                provideRouteAlternatives: false // 簡化：先只顯示一條最佳路徑
            };

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
                    
                    // 1. 顯示總結資訊
                    document.getElementById("summary-text").innerHTML = 
                        `<strong>${route.distance.text} • ${route.duration.text}</strong>`;

                    // 2. 產生詳細文字指引 (Magic happens here!)
                    renderDirectionsPanel(route.steps);

                } else {
                    document.getElementById("summary-text").innerHTML = `<span style="color:red">找不到路線</span>`;
                    document.getElementById("directions-panel").style.display = "none";
                }
            });
        }

        // --- 核心功能：把路線步驟轉成漂亮的 HTML 列表 ---
        function renderDirectionsPanel(steps) {
            const panel = document.getElementById("directions-panel");
            panel.innerHTML = ""; // 清空舊資料
            panel.style.display = "block"; // 顯示面板

            // 標題
            panel.innerHTML += `<div style="padding:15px 15px 0 15px; font-weight:bold; color:#1a73e8;">詳細路線指引</div>`;

            steps.forEach(step => {
                let icon = "";
                let color = "#666";
                let instruction = step.instructions; // Google 給的原始指引文字
                let detail = ""; // 額外資訊 (車號、站數)

                // 判斷這一步驟是什麼模式
                if (step.travel_mode === 'WALKING') {
                    icon = "🚶";
                    detail = `步行 ${step.distance.text}`;
                } else if (step.travel_mode === 'TRANSIT') {
                    const line = step.transit.line;
                    // 根據車種給不同圖示和顏色
                    if (line.vehicle.type === 'SUBWAY') {
                        icon = "🚇";
                        color = line.color || "#d32f2f"; // 如果 API 沒給顏色，預設紅
                    } else if (line.vehicle.type === 'BUS') {
                        icon = "🚌";
                        color = line.color || "#1976d2"; // 預設藍
                    } else if (line.vehicle.type === 'HEAVY_RAIL' || line.vehicle.type === 'TRAIN') {
                        icon = "🚆";
                        color = "#fbc02d";
                    } else if (line.vehicle.type === 'HIGH_SPEED_TRAIN') {
                        icon = "🚄";
                        color = "#ff6f00";
                    }

                    // 製作漂亮的車號標籤
                    instruction = `<span class="transit-badge" style="background:${color}">${line.short_name || line.name}</span> 開往 ${step.transit.headsign}`;
                    detail = `搭乘 ${step.transit.num_stops} 站 • ${step.duration.text}`;
                } else if (step.travel_mode === 'DRIVING' || step.travel_mode === 'TWO_WHEELER') {
                    icon = "⬆️"; // 簡單箭頭，實際可根據 maneuver (左轉/右轉) 判斷，這裡先簡化
                    detail = step.distance.text;
                }

                // 組合 HTML
                const html = `
                    <div class="step-item">
                        <div class="step-icon">${icon}</div>
                        <div class="step-content">
                            <div class="step-instruction">${instruction}</div>
                            <div class="step-detail">${detail}</div>
                        </div>
                    </div>
                `;
                panel.innerHTML += html;
            });
        }

        function clearInput(inputId, btnId) {
            const input = document.getElementById(inputId); input.value = ""; input.focus(); toggleClearBtn(inputId, btnId);
        }
        function toggleClearBtn(inputId, btnId) {
            const input = document.getElementById(inputId);
            const btn = document.getElementById(btnId);
            btn.style.display = input.value.length > 0 ? "block" : "none";
        }
    </script>
</body>
</html>