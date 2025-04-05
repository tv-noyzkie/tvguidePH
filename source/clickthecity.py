import requests
from bs4 import BeautifulSoup
import re
from datetime import datetime, timedelta
import xml.etree.ElementTree as ET
from xml.dom import minidom

def fetch_channels():
    url = "https://www.clickthecity.com/tv/channels/"
    response = requests.get(url)
    if response.status_code != 200:
        print("Failed to fetch channels.")
        return []

    soup = BeautifulSoup(response.text, "html.parser")
    channel_blocks = soup.find_all("div", class_="col")

    channels = []
    for block in channel_blocks:
        match = re.search(r'netid=(\d+)', str(block))
        if match:
            channel_id = match.group(1)
            channel_name = block.img["alt"] if block.img else "Unknown"
            channels.append({"channel_id": channel_id, "channel_name": channel_name})

    return channels

def fetch_schedule(channel_id, channel_name):
    url = f"https://www.clickthecity.com/tv/channels/?netid={channel_id}"
    response = requests.get(url)
    if response.status_code != 200:
        print(f"Failed to fetch schedule for channel {channel_id}.")
        return []

    soup = BeautifulSoup(response.text, "html.parser")
    schedule = []
    for row in soup.find_all("tr"):
        time_match = re.search(r'cTme.*?>(.*?)<', str(row))
        title_match = re.search(r'<a.*?>(.*?)<\/a>', str(row))

        if time_match and title_match:
            start_time = time_match.group(1)
            start_dt = datetime.strptime(start_time, "%I:%M %p")
            end_dt = start_dt + timedelta(hours=1)
            schedule.append({
                "start": start_dt.strftime("%Y%m%d%H%M%S"),
                "end": end_dt.strftime("%Y%m%d%H%M%S"),
                "title": title_match.group(1),
                "channel_name": channel_name
            })

    return schedule

def generate_epg():
    print("Starting to generate EPG...")
    channels = fetch_channels()
    if not channels:
        print("No channels found. Exiting.")
        return

    print(f"Fetched {len(channels)} channels successfully!")
    root = ET.Element("tv")

    # Create channel elements first
    for channel in channels:
        channel_elem = ET.SubElement(root, "channel", id=channel["channel_id"])
        name_elem = ET.SubElement(channel_elem, "display-name")
        name_elem.text = channel["channel_name"]

    # Fetch and add programme data
    for channel in channels:
        print(f"Fetching schedule for channel: {channel['channel_name']}")
        schedule = fetch_schedule(channel["channel_id"], channel["channel_name"])

        if not schedule:
            print(f"Skipping {channel['channel_name']}, no schedule available.")
            continue

        for show in schedule:
            programme_elem = ET.SubElement(
                root, "programme", start=show["start"], stop=show["end"], channel=channel["channel_id"], display_name=show["channel_name"]
            )
            title_elem = ET.SubElement(programme_elem, "title")
            title_elem.text = show["title"]

    # Pretty print XML
    xml_str = minidom.parseString(ET.tostring(root, encoding='utf-8')).toprettyxml(indent="  ")
    epg_path = 'output/individual/clickthecity.xml'
    print(f"Writing EPG data to {epg_path}...")

    with open(epg_path, "w", encoding="utf-8") as xml_file:
        xml_file.write(xml_str)

    print("EPG generation completed and written to output/individual/clickthecity.xml!")

if __name__ == "__main__":
    generate_epg()
