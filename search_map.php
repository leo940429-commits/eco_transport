<?php
// [後端 PHP] 讀取 XML 係數
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
    <title>節能交通搜尋 (自動完成版)</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        /* CSS 樣式保持不變 */
        html, body { height: 100%; margin: 0; padding: 0; font-family: 'Roboto', "微軟正黑體", sans-serif; }
        #map { height: 100%; width: 100%; }
        #left-panel { position: absolute; top: 10px; left: 10px; z-index: 5; width: 380px; max-height: 90vh; display: flex; flex-direction: column; gap: 10px; }
        #search-box { background-color: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        #directions-panel { background-color: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); overflow-y: auto; display: none; padding-bottom: 10px;}
        .input-wrapper { position: relative; margin-bottom: 10px; }
        input[type="text"] { width: 100%; padding: 10px 35px 10px 10px; border: 1px solid #dadce0; border-radius: 4px; box-sizing: border-box; outline: none; }
        input[type="text"]:focus { border-color: #4285f4; box-shadow: 0 0 0 2px rgba(66,133,244,0.2); }
        .clear-btn { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #70757a; display: none; }
        #search-btn { width: 100%; padding: 10px; background-color: #1a73e8; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; }
        #search-btn:hover { background-color: #1557b0; }
        #mode-selector { display: none; margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px; overflow-x: auto; white-space: nowrap; padding-bottom: 5px; }
        .mode-btn { display: inline-block; padding: 6px 12px; margin-right: 5px; border: 1px solid #dadce0; border-radius: 20px; background: white; color: #5f6368; cursor: pointer; transition: 0.2s;}
        .mode-btn:hover { background-color: #f1f3f4; }
        .mode-btn.active { background-color: #e8f0fe; color: #1a73e8; border-color: #1a73e8; font-weight: bold; }
        #sort-buttons { display: none; margin-top: 10px; gap: 5px; justify-content: space-between; }
        .sort-btn { flex: 1; padding: 8px; border: 1px solid #dadce0; border-radius: 4px; background: white; cursor: pointer; font-size: 13px; text-align: center; }
        .sort-btn:hover { background-color: #f8f9fa; }
        .sort-btn.active[data-sort="time"] { background-color: #fce8e6; color: #c5221f; border-color: #c5221f; font-weight: bold; }
        .sort-btn.active[data-sort="carbon"] { background-color: #e6f4ea; color: #137333; border-color: #137333; font-weight: bold; }
        .sort-btn.active[data-sort="price"] { background-color: #fff8e1; color: #f9ab00; border-color: #f9ab00; font-weight: bold; }
        #summary-box { margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px; display: none; border-left: 4px solid #1a73e8; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 14px; }
        .total-carbon { color: #137333; font-weight: bold; }
        .total-price { color: #f9ab00; font-weight: bold; text-shadow: 0px 0px 1px #999; }
        .step-item { padding: 12px 15px; border-bottom: 1px solid #eee; display: flex; align-items: flex-start; justify-content: space-between; }
        .step-info { display: flex; align-items: flex-start; flex: 1; }
        .step-icon { font-size: 20px; margin-right: 15px; min-width: 30px; text-align: center; }
        .step-content { font-size: 14px; color: #333; }
        .transit-badge { display: inline-block; padding: 2px 6px; border-radius: 4px; color: white; font-size: 12px; font-weight: bold; margin-right: 5px; }
        .focus-btn { background: white; border: 1px solid #dadce0; color: #1a73e8; cursor: pointer; padding: 5px 10px; border-radius: 15px; font-size: 12px; margin-left: 10px; white-space: nowrap; transition: 0.2s; }
        .focus-btn:hover { background: #e8f0fe; border-color: #1a73e8; }
        h3 { margin: 0 0 10px 0; font-size: 18px; }
        /* 修正 Autocomplete 下拉選單有時候被擋住的問題 */
        .pac-container { z-index: 10000 !important; }
    </style>
</head>
<body>

    <div id="left-panel">
        <div id="search-box">
            <h3>🌱 節能路徑規劃</h3>
            
            <div class="input-wrapper">
                <input type="text" id="origin-input" placeholder="起點 (例如: 台北車站)" oninput="toggleClearBtn('origin-input', 'clear-origin')">
                <span id="clear-origin" class="clear-btn" onclick="clearInput('origin-input', 'clear-origin')">&times;</span>
            </div>

            <div class="input-wrapper">
                <input type="text" id="dest-input" placeholder="終點 (例如: 101)" oninput="toggleClearBtn('dest-input', 'clear-dest')">
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
            
            <div id="sort-buttons">
                <button class="sort-btn active" data-sort="time" onclick="sortRoutes('time')">⚡ 我很急</button>
                <button class="sort-btn" data-sort="carbon" onclick="sortRoutes('carbon')">🌱 愛地球</button>
                <button class="sort-btn" data-sort="price" onclick="sortRoutes('price')">💰 省荷包</button>
            </div>

            <div id="summary-box">
                <div class="summary-row"><span>⏱️ 時間/距離：</span><span id="sum-dist-time" style="font-weight:bold;">--</span></div>
                <div class="summary-row"><span>🌍 總碳排放：</span><span id="sum-carbon" class="total-carbon">0 kg</span></div>
                <div class="summary-row"><span>💰 預估花費：</span><span id="sum-price" class="total-price">$0</span></div>
            </div>
        </div>
        <div id="directions-panel"></div>
    </div>

    <div id="map"></div>

    <!-- [關鍵修改] 網址最後面加上了 &libraries=places -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAFy0ChBy24ECuNAzspWl9-sYJ4Cp_J48g&callback=initMap&libraries=places" async defer></script>

    <script>
        const carbonRates = <?php echo $json_rates; ?>;
        let map, directionsService, directionsRenderer, stepInfoWindow;
        let currentRouteSteps = [];
        let currentTravelMode = 'TRANSIT';
        let currentTransitType = 'SUBWAY';
        let originalDirectionsResult = null;
        let calculatedRoutes = [];

        function initMap() {
            map = new google.maps.Map(document.getElementById("map"), {
                center: { lat: 25.0478, lng: 121.5170 }, zoom: 14, mapTypeControl: false, fullscreenControl: false
            });
            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({ map: map, suppressMarkers: false });
            stepInfoWindow = new google.maps.InfoWindow();

            // --- [新增] 初始化自動完成功能 ---
            initAutocomplete();

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

        // --- [新增] 自動完成設定函式 ---
        function initAutocomplete() {
            const options = {
                componentRestrictions: { country: "tw" }, // 限制只搜尋台灣
                fields: ["formatted_address", "geometry", "name"], // 只抓取需要的欄位 (省錢)
            };

            const originInput = document.getElementById("origin-input");
            const destInput = document.getElementById("dest-input");

            // 綁定輸入框
            new google.maps.places.Autocomplete(originInput, options);
            new google.maps.places.Autocomplete(destInput, options);
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
                provideRouteAlternatives: true
            };

            if (currentTravelMode === 'TRANSIT' && currentTransitType !== '') {
                request.transitOptions = {
                    modes: [google.maps.TransitMode[currentTransitType]],
                    routingPreference: 'FEWER_TRANSFERS'
                };
            }

            directionsService.route(request, (result, status) => {
                if (status === "OK") {
                    originalDirectionsResult = result;
                    processAllRoutes(result.routes);
                    document.getElementById("sort-buttons").style.display = "flex";
                    sortRoutes('time');
                } else {
                    alert("找不到路線");
                }
            });
        }

        function processAllRoutes(routes) {
            calculatedRoutes = [];
            routes.forEach((route, index) => {
                let calculated = calculateMetrics(route);
                calculatedRoutes.push({
                    originalIndex: index,
                    totalCarbon: calculated.carbon,
                    totalPrice: calculated.price,
                    durationValue: route.legs[0].duration.value,
                    data: route
                });
            });
        }

        function calculateMetrics(route) {
            let totalCarbon = 0;
            let totalPrice = 0;
            route.legs[0].steps.forEach(step => {
                let distanceKm = step.distance.value / 1000;
                let typeKey = 'WALKING'; 
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

        function sortRoutes(preference) {
            document.querySelectorAll('.sort-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`.sort-btn[data-sort="${preference}"]`).classList.add('active');

            calculatedRoutes.sort((a, b) => {
                if (preference === 'time') return a.durationValue - b.durationValue;
                if (preference === 'carbon') return a.totalCarbon - b.totalCarbon;
                if (preference === 'price') return a.totalPrice - b.totalPrice;
            });

            const bestRoute = calculatedRoutes[0];
            directionsRenderer.setDirections(originalDirectionsResult);
            directionsRenderer.setRouteIndex(bestRoute.originalIndex);
            stepInfoWindow.close();
            
            const routeToShow = bestRoute.data;
            routeToShow.calculatedCarbon = bestRoute.totalCarbon;
            routeToShow.calculatedPrice = bestRoute.totalPrice;
            renderDirectionsPanel(routeToShow);
        }

        function renderDirectionsPanel(route) {
            const panel = document.getElementById("directions-panel");
            const summaryBox = document.getElementById("summary-box");
            
            panel.innerHTML = `<div style="padding:15px 15px 0 15px; font-weight:bold; color:#555;">詳細路線</div>`;
            panel.style.display = "block";
            summaryBox.style.display = "block";

            document.getElementById("sum-dist-time").innerText = `${route.legs[0].distance.text} / ${route.legs[0].duration.text}`;
            document.getElementById("sum-carbon").innerText = `${route.calculatedCarbon.toFixed(2)} kg`;
            document.getElementById("sum-price").innerText = `$${Math.round(route.calculatedPrice)}`;

            currentRouteSteps = route.legs[0].steps;
            currentRouteSteps.forEach((step, index) => {
                let icon = "🚶";
                let instruction = step.instructions;
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
                        <div class="step-info">
                            <div class="step-icon">${icon}</div>
                            <div class="step-content">
                                <div style="font-weight:500;">${instruction} ${step.instructions}</div>
                                <div style="font-size:13px; color:#666;">${step.distance.text} • ${step.duration.text}</div>
                            </div>
                        </div>
                        <button class="focus-btn" onclick="focusOnStep(${index})">📍 查看</button>
                    </div>
                `;
            });
        }

        function focusOnStep(index) {
            const step = currentRouteSteps[index];
            map.panTo(step.start_location);
            map.setZoom(16);
            let tempDiv = document.createElement("div"); tempDiv.innerHTML = step.instructions;
            let cleanText = tempDiv.textContent || tempDiv.innerText || "";
            stepInfoWindow.setContent(`<div style="padding:5px; font-weight:bold;">${cleanText}</div>`);
            stepInfoWindow.setPosition(step.start_location);
            stepInfoWindow.open(map);
        }

        function clearInput(id, btnId) { document.getElementById(id).value = ""; document.getElementById(id).focus(); toggleClearBtn(id, btnId); }
        function toggleClearBtn(id, btnId) { document.getElementById(btnId).style.display = document.getElementById(id).value.length > 0 ? "block" : "none"; }
    </script>
</body>
</html>