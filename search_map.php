<?php
// --- [後端 PHP 區域] ---
// 讀取碳排係數 XML
$xml = simplexml_load_file("carbon_data.xml");
$rates = [];
foreach ($xml->item as $item) {
    $type = (string)$item['type'];
    $rates[$type] = [
        'name' => (string)$item->name,
        'co2'  => (float)$item->co2_factor,
        'base' => (float)$item->price_base,
        'p_km' => (float)$item->price_per_km
    ];
}
$json_rates = json_encode($rates);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>節能交通搜尋 (多重篩選版)</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        /* CSS 樣式區 */
        html, body { height: 100%; margin: 0; padding: 0; font-family: 'Roboto', "微軟正黑體", sans-serif; }
        #map { height: 100%; width: 100%; }
        
        #left-panel { position: absolute; top: 10px; left: 10px; z-index: 5; width: 380px; max-height: 90vh; display: flex; flex-direction: column; gap: 10px; }
        #search-box { background-color: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        #directions-panel { background-color: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); overflow-y: auto; display: none; padding-bottom: 10px;}

        /* 輸入框與按鈕 */
        .input-wrapper { position: relative; margin-bottom: 10px; }
        input[type="text"] { width: 100%; padding: 10px 35px 10px 10px; border: 1px solid #dadce0; border-radius: 4px; box-sizing: border-box; outline: none; }
        input[type="text"]:focus { border-color: #4285f4; box-shadow: 0 0 0 2px rgba(66,133,244,0.2); }
        .clear-btn { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #70757a; display: none; }
        #search-btn { width: 100%; padding: 10px; background-color: #1a73e8; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; }
        #search-btn:hover { background-color: #1557b0; }

        /* 第一層：交通工具選擇 (Mode) */
        #mode-selector { display: none; margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px; overflow-x: auto; white-space: nowrap; padding-bottom: 5px; }
        .mode-btn { display: inline-block; padding: 6px 12px; margin-right: 5px; border: 1px solid #dadce0; border-radius: 20px; background: white; color: #5f6368; cursor: pointer; transition: 0.2s;}
        .mode-btn:hover { background-color: #f1f3f4; }
        .mode-btn.active { background-color: #e8f0fe; color: #1a73e8; border-color: #1a73e8; font-weight: bold; }

        /* 第二層：需求偏好按鈕 (Sort) - 預設隱藏 */
        #sort-buttons { display: none; margin-top: 10px; gap: 5px; justify-content: space-between; }
        .sort-btn { flex: 1; padding: 8px; border: 1px solid #dadce0; border-radius: 4px; background: white; cursor: pointer; font-size: 13px; text-align: center; }
        .sort-btn:hover { background-color: #f8f9fa; }
        
        /* 偏好按鈕的啟用狀態顏色 */
        .sort-btn.active[data-sort="time"] { background-color: #fce8e6; color: #c5221f; border-color: #c5221f; font-weight: bold; } /* 急：紅色 */
        .sort-btn.active[data-sort="carbon"] { background-color: #e6f4ea; color: #137333; border-color: #137333; font-weight: bold; } /* 綠：綠色 */
        .sort-btn.active[data-sort="price"] { background-color: #fff8e1; color: #f9ab00; border-color: #f9ab00; font-weight: bold; } /* 窮：金黃色 */

        /* 總結區塊 */
        #summary-box { margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px; display: none; border-left: 4px solid #1a73e8; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 14px; }
        .total-carbon { color: #137333; font-weight: bold; }
        .total-price { color: #f9ab00; font-weight: bold; text-shadow: 0px 0px 1px #999; }

        /* 詳細步驟 */
        .step-item { padding: 12px 15px; border-bottom: 1px solid #eee; display: flex; align-items: flex-start; }
        .step-icon { font-size: 20px; margin-right: 15px; min-width: 30px; text-align: center; }
        .step-content { font-size: 14px; color: #333; width: 100%; }
        .transit-badge { display: inline-block; padding: 2px 6px; border-radius: 4px; color: white; font-size: 12px; font-weight: bold; margin-right: 5px; }
        .eco-tag { font-size: 12px; color: #137333; background: #e6f4ea; padding: 1px 5px; border-radius: 4px; }
        
        h3 { margin: 0 0 10px 0; font-size: 18px; }
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

            <!-- 1. 交通工具選擇 -->
            <div id="mode-selector">
                <button class="mode-btn active" onclick="changeMode(this, 'TRANSIT', 'SUBWAY')">🚇 捷運</button>
                <button class="mode-btn" onclick="changeMode(this, 'TRANSIT', 'BUS')">🚌 公車</button>
                <button class="mode-btn" onclick="changeMode(this, 'TRANSIT', 'TRAIN')">🚆 鐵路</button>
                <button class="mode-btn" onclick="changeMode(this, 'BICYCLING', '')">🚲 自行車</button>
                <button class="mode-btn" onclick="changeMode(this, 'WALKING', '')">🚶 走路</button>
                <button class="mode-btn" onclick="changeMode(this, 'TWO_WHEELER', '')">🛵 機車</button>
                <button class="mode-btn" onclick="changeMode(this, 'DRIVING', '')">🚗 開車</button>
            </div>
            
            <!-- 2. 需求偏好選擇 (搜尋後出現) -->
            <div id="sort-buttons">
                <button class="sort-btn active" data-sort="time" onclick="sortRoutes('time')">⚡ 我很急 (最快)</button>
                <button class="sort-btn" data-sort="carbon" onclick="sortRoutes('carbon')">🌱 愛地球 (低碳)</button>
                <button class="sort-btn" data-sort="price" onclick="sortRoutes('price')">💰 省荷包 (最省)</button>
            </div>

            <!-- 總結數據 -->
            <div id="summary-box">
                <div class="summary-row"><span>⏱️ 時間/距離：</span><span id="sum-dist-time" style="font-weight:bold;">--</span></div>
                <div class="summary-row"><span>🌍 總碳排放：</span><span id="sum-carbon" class="total-carbon">0 kg</span></div>
                <div class="summary-row"><span>💰 預估花費：</span><span id="sum-price" class="total-price">$0</span></div>
            </div>
        </div>

        <div id="directions-panel"></div>
    </div>

    <div id="map"></div>

    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAFy0ChBy24ECuNAzspWl9-sYJ4Cp_J48g&callback=initMap" async defer></script>

    <script>
        const carbonRates = <?php echo $json_rates; ?>;
        
        let map, directionsService, directionsRenderer;
        let currentTravelMode = 'TRANSIT';
        let currentTransitType = 'SUBWAY';
        
        // 儲存 Google 回傳的所有候選路線
        let allCandidateRoutes = [];

        function initMap() {
            map = new google.maps.Map(document.getElementById("map"), {
                center: { lat: 25.0478, lng: 121.5170 }, zoom: 14, mapTypeControl: false, fullscreenControl: false
            });
            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({ map: map, suppressMarkers: false });

            document.getElementById("search-btn").addEventListener("click", function() {
                document.getElementById("mode-selector").style.display = "block";
                calculateRoute();
            });

            // 街景控制
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
                provideRouteAlternatives: true // [關鍵] 請 Google 多給幾條路線讓我們挑
            };

            if (currentTravelMode === 'TRANSIT' && currentTransitType !== '') {
                request.transitOptions = {
                    modes: [google.maps.TransitMode[currentTransitType]],
                    routingPreference: 'FEWER_TRANSFERS'
                };
            }

            directionsService.route(request, (result, status) => {
                if (status === "OK") {
                    // 1. 把所有回傳的路線都先算好碳排和價錢
                    processAllRoutes(result.routes);
                    
                    // 2. 顯示排序按鈕
                    document.getElementById("sort-buttons").style.display = "flex";
                    
                    // 3. 預設先用「最快 (Time)」來排序並顯示
                    sortRoutes('time'); 

                } else {
                    alert("找不到路線");
                }
            });
        }

        // --- 新邏輯：預先處理所有路線 ---
        function processAllRoutes(routes) {
            allCandidateRoutes = []; // 清空

            routes.forEach((route, index) => {
                // 幫每一條路線算出總碳排、總價錢
                let calculated = calculateMetrics(route);
                
                // 把算好的數據塞回這個路線物件裡，方便等一下排序
                route.totalCarbon = calculated.carbon;
                route.totalPrice = calculated.price;
                route.originalIndex = index; // 記住它是第幾條，等等渲染要用

                allCandidateRoutes.push(route);
            });
        }

        // --- 新邏輯：計算單條路線的指標 (不渲染 HTML，只算數) ---
        function calculateMetrics(route) {
            let totalCarbon = 0;
            let totalPrice = 0;

            route.legs[0].steps.forEach(step => {
                let distanceKm = step.distance.value / 1000;
                let typeKey = 'WALKING'; // Default

                if (step.travel_mode === 'TRANSIT') {
                    let vType = step.transit.line.vehicle.type;
                    if (vType === 'HEAVY_RAIL') vType = 'TRAIN';
                    typeKey = vType;
                } else if (step.travel_mode === 'DRIVING') typeKey = 'DRIVING';
                else if (step.travel_mode === 'TWO_WHEELER') typeKey = 'TWO_WHEELER';
                else if (step.travel_mode === 'BICYCLING') typeKey = 'BICYCLING';

                let rate = carbonRates[typeKey] || carbonRates['WALKING'];
                totalCarbon += distanceKm * rate.co2;
                totalPrice += rate.base + (distanceKm * rate.p_km);
            });

            return { carbon: totalCarbon, price: totalPrice };
        }

        // --- 排序功能 ---
        function sortRoutes(preference) {
            // 1. 更新按鈕樣式
            document.querySelectorAll('.sort-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`.sort-btn[data-sort="${preference}"]`).classList.add('active');

            // 2. 進行排序 (數字越小排越前面)
            allCandidateRoutes.sort((a, b) => {
                if (preference === 'time') {
                    return a.legs[0].duration.value - b.legs[0].duration.value;
                } else if (preference === 'carbon') {
                    return a.totalCarbon - b.totalCarbon;
                } else if (preference === 'price') {
                    return a.totalPrice - b.totalPrice;
                }
            });

            // 3. 取出第一名 (冠軍路線)
            const bestRoute = allCandidateRoutes[0];
            
            // 4. 畫在地圖上
            directionsRenderer.setDirections({routes: allCandidateRoutes}); // 還是要把所有路線給 Renderer
            directionsRenderer.setRouteIndex(allCandidateRoutes.indexOf(bestRoute)); // 但指定顯示冠軍這條

            // 5. 更新左側面板內容
            renderDirectionsPanel(bestRoute);
        }

        // --- 渲染面板 (跟之前差不多，只是加上數據顯示) ---
        function renderDirectionsPanel(route) {
            const panel = document.getElementById("directions-panel");
            const summaryBox = document.getElementById("summary-box");
            
            panel.innerHTML = `<div style="padding:15px 15px 0 15px; font-weight:bold; color:#555;">詳細路線</div>`;
            panel.style.display = "block";
            summaryBox.style.display = "block";

            // 更新總結
            document.getElementById("sum-dist-time").innerText = `${route.legs[0].distance.text} / ${route.legs[0].duration.text}`;
            document.getElementById("sum-carbon").innerText = `${route.totalCarbon.toFixed(2)} kg`;
            document.getElementById("sum-price").innerText = `$${Math.round(route.totalPrice)}`;

            // 顯示步驟
            route.legs[0].steps.forEach(step => {
                let icon = "🚶";
                let instruction = step.instructions;
                
                // 簡單的圖示判斷
                if (step.travel_mode === 'TRANSIT') {
                    const vType = step.transit.line.vehicle.type;
                    if(vType === 'SUBWAY') icon = "🚇";
                    else if(vType === 'BUS') icon = "🚌";
                    else icon = "🚆";
                    instruction = `<span class="transit-badge" style="background:#666">${step.transit.line.short_name || step.transit.line.name}</span>`;
                } else if (step.travel_mode === 'DRIVING') icon = "🚗";
                else if (step.travel_mode === 'TWO_WHEELER') icon = "🛵";

                panel.innerHTML += `
                    <div class="step-item">
                        <div class="step-icon">${icon}</div>
                        <div class="step-content">
                            <div style="font-weight:500;">${instruction} ${step.instructions}</div>
                            <div style="font-size:13px; color:#666;">${step.distance.text} • ${step.duration.text}</div>
                        </div>
                    </div>
                `;
            });
        }

        function clearInput(id, btnId) { document.getElementById(id).value = ""; document.getElementById(id).focus(); toggleClearBtn(id, btnId); }
        function toggleClearBtn(id, btnId) { document.getElementById(btnId).style.display = document.getElementById(id).value.length > 0 ? "block" : "none"; }
    </script>
</body>
</html>