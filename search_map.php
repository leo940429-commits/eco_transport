<?php
// --- [後端 PHP 區域] ---
// 1. 讀取 XML 檔案
$xml = simplexml_load_file("carbon_data.xml");

// 2. 將 XML 資料轉換成 PHP 陣列，方便處理
$rates = [];
foreach ($xml->item as $item) {
    // 取得屬性 type (例如 BUS, SUBWAY)
    $type = (string)$item['type'];
    $rates[$type] = [
        'name' => (string)$item->name,
        'co2'  => (float)$item->co2_factor,
        'base' => (float)$item->price_base,
        'p_km' => (float)$item->price_per_km
    ];
}

// 3. 把資料轉成 JSON，傳給前端 JavaScript 使用
// 這一步是「後端傳輸資料給前端」的關鍵
$json_rates = json_encode($rates);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>節能交通搜尋 (PHP後端運算版)</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        /* 樣式保持不變，新增數據顯示的樣式 */
        html, body { height: 100%; margin: 0; padding: 0; font-family: 'Roboto', "微軟正黑體", sans-serif; }
        #map { height: 100%; width: 100%; }
        #left-panel { position: absolute; top: 10px; left: 10px; z-index: 5; width: 360px; max-height: 90vh; display: flex; flex-direction: column; gap: 10px; }
        #search-box { background-color: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        #directions-panel { background-color: white; padding: 0; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); overflow-y: auto; display: none; }
        
        .step-item { padding: 15px; border-bottom: 1px solid #eee; display: flex; align-items: flex-start; }
        .step-icon { font-size: 20px; margin-right: 15px; min-width: 30px; text-align: center; }
        .step-content { font-size: 14px; color: #333; width: 100%; }
        .step-instruction { font-weight: 500; margin-bottom: 4px; }
        .transit-badge { display: inline-block; padding: 2px 6px; border-radius: 4px; color: white; font-size: 12px; font-weight: bold; margin-right: 5px; }

        /* 新增：碳排與價格標籤 */
        .eco-tag { font-size: 12px; color: #137333; background: #e6f4ea; padding: 2px 6px; border-radius: 4px; margin-right: 5px; }
        .price-tag { font-size: 12px; color: #c5221f; background: #fce8e6; padding: 2px 6px; border-radius: 4px; }
        
        /* 總結區塊 */
        #summary-box {
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            display: none; /* 預設隱藏 */
        }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 14px; }
        .total-carbon { color: #137333; font-weight: bold; }
        .total-price { color: #c5221f; font-weight: bold; }

        /* 通用樣式 */
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
    </style>
</head>
<body>

    <div id="left-panel">
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
                <button class="mode-btn" onclick="changeMode(this, 'DRIVING', '')">🚗 開車</button>
            </div>
            
            <!-- 總花費與總碳排顯示區 -->
            <div id="summary-box">
                <div class="summary-row">
                    <span>總距離 / 時間：</span>
                    <span id="sum-dist-time" style="font-weight:bold;">--</span>
                </div>
                <div class="summary-row">
                    <span>🌍 總碳排放：</span>
                    <span id="sum-carbon" class="total-carbon">0 kg</span>
                </div>
                <div class="summary-row">
                    <span>💰 預估花費：</span>
                    <span id="sum-price" class="total-price">$0</span>
                </div>
            </div>
        </div>

        <div id="directions-panel"></div>
    </div>

    <div id="map"></div>

    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAFy0ChBy24ECuNAzspWl9-sYJ4Cp_J48g&callback=initMap" async defer></script>

    <script>
        // [關鍵] 接收 PHP 傳來的 XML 資料
        // PHP 的陣列在這裡變成了 JavaScript 的物件
        const carbonRates = <?php echo $json_rates; ?>;
        console.log("從 XML 讀取的費率表：", carbonRates);

        let map, directionsService, directionsRenderer;
        let currentTravelMode = 'TRANSIT';
        let currentTransitType = 'SUBWAY';

        function initMap() {
            map = new google.maps.Map(document.getElementById("map"), {
                center: { lat: 25.0478, lng: 121.5170 },
                zoom: 14, mapTypeControl: false, fullscreenControl: false
            });

            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({ map: map, suppressMarkers: false });

            document.getElementById("search-btn").addEventListener("click", function() {
                document.getElementById("mode-selector").style.display = "block";
                calculateRoute();
            });

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
                provideRouteAlternatives: false
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
                    renderDirectionsPanel(route); // 呼叫詳細顯示函式
                } else {
                    alert("找不到路線");
                }
            });
        }

        // --- 核心運算函式 ---
        function renderDirectionsPanel(route) {
            const panel = document.getElementById("directions-panel");
            const summaryBox = document.getElementById("summary-box");
            
            panel.innerHTML = `<div style="padding:15px 15px 0 15px; font-weight:bold; color:#1a73e8;">詳細路線與碳排分析</div>`;
            panel.style.display = "block";
            summaryBox.style.display = "block";

            // 初始化累加變數
            let totalCarbon = 0;
            let totalPrice = 0;

            route.steps.forEach(step => {
                let icon = "";
                let color = "#666";
                let instruction = step.instructions;
                let detail = ""; 
                
                // 1. 取得這一步的距離 (公里)
                let distanceKm = step.distance.value / 1000;
                
                // 2. 判斷交通工具類型，並查表 (carbonRates)
                let typeKey = "WALKING"; // 預設走路
                
                if (step.travel_mode === 'TRANSIT') {
                    // Google 的類型 (如 SUBWAY) 對應到我們 XML 的 Key
                    let vType = step.transit.line.vehicle.type;
                    if (vType === 'HEAVY_RAIL') vType = 'TRAIN'; // 修正台鐵的名稱
                    typeKey = vType;

                    // 視覺處理
                    const line = step.transit.line;
                    if (vType === 'SUBWAY') { icon = "🚇"; color = line.color || "#d32f2f"; }
                    else if (vType === 'BUS') { icon = "🚌"; color = line.color || "#1976d2"; }
                    else if (vType === 'TRAIN') { icon = "🚆"; color = "#fbc02d"; }
                    else if (vType === 'HIGH_SPEED_TRAIN') { icon = "🚄"; color = "#ff6f00"; }
                    
                    instruction = `<span class="transit-badge" style="background:${color}">${line.short_name || line.name}</span> 開往 ${step.transit.headsign}`;
                    detail = `搭乘 ${step.transit.num_stops} 站`;

                } else if (step.travel_mode === 'DRIVING') {
                    typeKey = 'DRIVING'; icon = "🚗";
                } else if (step.travel_mode === 'TWO_WHEELER') {
                    typeKey = 'TWO_WHEELER'; icon = "🛵";
                } else if (step.travel_mode === 'BICYCLING') {
                    typeKey = 'BICYCLING'; icon = "🚲";
                } else {
                    icon = "🚶"; detail = "步行";
                }

                // 3. 數學運算：計算這一段的碳排與價格
                // 檢查 XML 有沒有定義這個交通工具，如果沒有就用走路(0)
                let rate = carbonRates[typeKey] || carbonRates['WALKING'];
                
                // 碳排 = 距離 * 係數
                let stepCarbon = distanceKm * rate.co2;
                
                // 價格 = 基本費 + (距離 * 每公里費率)
                let stepPrice = rate.base + (distanceKm * rate.p_km);

                // 累加到總數
                totalCarbon += stepCarbon;
                totalPrice += stepPrice;

                // 組合 HTML
                panel.innerHTML += `
                    <div class="step-item">
                        <div class="step-icon">${icon}</div>
                        <div class="step-content">
                            <div class="step-instruction">${instruction}</div>
                            <div class="step-detail">
                                ${step.distance.text} • ${step.duration.text} <br>
                                <span class="eco-tag">🌱 碳排 ${stepCarbon.toFixed(2)}kg</span>
                                <span class="price-tag">💰 預估 $${Math.round(stepPrice)}</span>
                            </div>
                        </div>
                    </div>
                `;
            });

            // 更新總結區塊
            document.getElementById("sum-dist-time").innerText = `${route.distance.text} / ${route.duration.text}`;
            document.getElementById("sum-carbon").innerText = `${totalCarbon.toFixed(2)} kg CO2e`;
            document.getElementById("sum-price").innerText = `$${Math.round(totalPrice)}`;
        }

        function clearInput(inputId, btnId) { const input = document.getElementById(inputId); input.value = ""; input.focus(); toggleClearBtn(inputId, btnId); }
        function toggleClearBtn(inputId, btnId) { const input = document.getElementById(inputId); const btn = document.getElementById(btnId); btn.style.display = input.value.length > 0 ? "block" : "none"; }
    </script>
</body>
</html>