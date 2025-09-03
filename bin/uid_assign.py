#!/usr/bin/env python3
import minimalmodbus
import serial.tools.list_ports

def scan_uids(port='/dev/ttyUSB0', baudrate=9600):
    found_uids = []
    for uid in range(1, 248):
        try:
            instrument = minimalmodbus.Instrument(port, uid)
            instrument.serial.baudrate = baudrate
            instrument.read_register(0, 0)  # Testregister
            found_uids.append(uid)
        except:
            continue
    return found_uids

if __name__ == "__main__":
    uids = scan_uids()
    print("Gefundene UIDs:", uids)
