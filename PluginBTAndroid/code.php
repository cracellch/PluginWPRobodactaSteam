<?php
/**
 * Plugin Name: Bluetooth Control
 * Description: Controla dispositivos Arduino vía Web Bluetooth desde una página de WordPress con shortcode.
 * Version: 1.9
 * Author: Enrique
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

function wp_bluetooth_control_ui() {
    ob_start();
    ?>
    <style>
        #ble-controller {
            max-width: 100%;
            width: 100%;
            box-sizing: border-box;
            padding: 20px;
            margin: 0 auto;
            background-color: #f9f9f9;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            font-family: 'Segoe UI', sans-serif;
        }

        #ble-controller h3 {
            margin-bottom: 15px;
            text-align: center;
            color: #333;
        }

        .bt-connect-wrapper {
            width: 100%;
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        .bt-connect-wrapper button {
            padding: 10px 16px;
            font-size: 15px;
            background-color: #0100FF;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            min-width: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .bt-connect-wrapper button:hover {
            background-color: #0030cc;
        }

        .bt-connect-wrapper button:active {
            background-color: #FF7500 !important;
        }

        .bt-connect-wrapper .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid white;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .bt-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            max-width: 400px;
            margin: 0 auto;
        }

        .bt-grid button {
            aspect-ratio: 1 / 1;
            width: 100%;
            background-color: #0100FF;
            border: none;
            border-radius: 6px;
            position: relative;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .bt-grid button:active {
            background-color: #FF7500 !important;
        }

        .triangle-up, .triangle-down, .triangle-left, .triangle-right {
            width: 0;
            height: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .triangle-up {
            border-left: 35px solid transparent;
            border-right: 35px solid transparent;
            border-bottom: 50px solid white;
        }

        .triangle-down {
            border-left: 35px solid transparent;
            border-right: 35px solid transparent;
            border-top: 50px solid white;
        }

        .triangle-left {
            border-top: 35px solid transparent;
            border-bottom: 35px solid transparent;
            border-right: 50px solid white;
        }

        .triangle-right {
            border-top: 35px solid transparent;
            border-bottom: 35px solid transparent;
            border-left: 50px solid white;
        }

        #bt-status {
            text-align: center;
            margin-top: 15px;
            font-weight: bold;
            color: #444;
        }

        @media (max-width: 600px) {
            .bt-grid {
                max-width: 100%;
            }

            .triangle-up, .triangle-down, .triangle-left, .triangle-right {
                transform: translate(-50%, -50%) scale(0.9);
            }
        }
    </style>

    <div id="ble-controller">
        <h3>Control Bluetooth</h3>

        <div class="bt-connect-wrapper">
            <button id="btn-connect" onclick="connectBLE()">
                🔌 Conectar
            </button>
        </div>

        <div class="bt-grid">
            <div></div>
            <button id="btn-2"><div class="triangle-up"></div></button>
            <div></div>
            <button id="btn-4"><div class="triangle-left"></div></button>
            <div></div>
            <button id="btn-6"><div class="triangle-right"></div></button>
            <div></div>
            <button id="btn-8"><div class="triangle-down"></div></button>
            <div></div>
        </div>

        <p id="bt-status">Estado: Desconectado</p>
    </div>

    <script>
    let bleCharacteristic;

    async function connectBLE() {
        const status = document.getElementById('bt-status');
        const button = document.getElementById('btn-connect');

        button.innerHTML = '<div class="spinner"></div> Conectando...';
        status.innerText = 'Estado: Buscando dispositivos... 🔍';

        try {
            const device = await navigator.bluetooth.requestDevice({
                acceptAllDevices: true,
                optionalServices: ['0000ffe0-0000-1000-8000-00805f9b34fb']
            });

            status.innerText = `Estado: Conectando a ${device.name || 'Dispositivo desconocido'}...`;

            const server = await device.gatt.connect();
            const service = await server.getPrimaryService('0000ffe0-0000-1000-8000-00805f9b34fb');
            bleCharacteristic = await service.getCharacteristic('0000ffe1-0000-1000-8000-00805f9b34fb');

            status.innerText = `✅ Conectado a: ${device.name || 'Dispositivo sin nombre'}`;
            button.innerHTML = '✅ Conectado';
        } catch (e) {
            console.error(e);
            status.innerText = '❌ Error al conectar: ' + e.message;
            button.innerHTML = '🔌 Conectar';
        }
    }

    function sendCommand(cmd) {
        if (bleCharacteristic) {
            const encoder = new TextEncoder();
            bleCharacteristic.writeValue(encoder.encode(cmd));
        } else {
            alert("No conectado a ningún dispositivo");
        }
    }

    function setupButtonEvents(id, onDown, onUp) {
        const btn = document.getElementById(id);
        if (btn) {
            btn.addEventListener('mousedown', () => sendCommand(onDown));
            btn.addEventListener('mouseup', () => sendCommand(onUp));
            btn.addEventListener('touchstart', e => {
                e.preventDefault();
                sendCommand(onDown);
            }, { passive: false });
            btn.addEventListener('touchend', e => {
                e.preventDefault();
                sendCommand(onUp);
            }, { passive: false });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupButtonEvents('btn-2', 'w', 't');
        setupButtonEvents('btn-4', 'a', 'f');
        setupButtonEvents('btn-6', 'd', 'h');
        setupButtonEvents('btn-8', 's', 'g');
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('bt_control', 'wp_bluetooth_control_ui');
