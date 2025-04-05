import requests
from bs4 import BeautifulSoup
import re
from datetime import datetime, timedelta
import xml.etree.ElementTree as ET
from xml.dom import minidom
import os

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
            img_tag = block.find("img")
            channel_name = img_tag["alt"] if img_tag and "alt" in img_tag.attrs else f"Channel ID {channel_id}"
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
    today = datetime.now(tz=datetime.now().astimezone().tzinfo).date()  # Get current date with timezone info

    for row in soup.find_all("tr"):
        time_match = re.search(r'cTme.*?>(.*?)<', str(row))
        title_match = re.search(r'<a.*?>(.*?)<\/a>', str(row))

        if time_match and title_match:
            start_time_str = time_match.group(1).strip()
            title = title_match.group(1).strip()

            try:
                start_dt_obj = datetime.strptime(start_time_str, "%I:%M %p")
                # Combine with today's date, keeping the local timezone
                start_datetime = datetime(today.year, today.month, today.day,
                                          start_dt_obj.hour, start_dt_obj.minute, 0,
                                          tzinfo=datetime.now().astimezone().tzinfo)

                # Assume each show is at least 30 minutes, adjust as needed
                end_datetime = start_datetime + timedelta(minutes=30)

                schedule.append({
                    "start": start_datetime.strftime("%Y%m%d%H%M%S %z"),
                    "end": end_datetime.strftime("%Y%m%d%H%M%S %z"),
                    "title": title,
                    "channel_name": channel_name
                })
            except ValueError as e:
                print(f"Error parsing time '{start_time_str}' for channel '{channel_name}': {e}")

    return schedule

def generate_epg():
    print("Starting to generate ClickTheCity EPG...")
    channels = fetch_channels()
    if not channels:
        print("No channels found. Exiting ClickTheCity EPG generation.")
        return

    print(f"Fetched {len(channels)} ClickTheCity channels successfully!")
    root = ET.Element("tv")

    # Create channel elements first
    for channel in channels:
        channel_elem = ET.SubElement(root, "channel", id=channel["channel_id"])
        name_elem = ET.SubElement(channel_elem, "display-name")
        name_elem.text = channel["channel_name"]

    # Fetch and add programme data
    for channel in channels:
        print(f"Fetching schedule for ClickTheCity channel: {channel['channel_name']}")
        schedule = fetch_schedule(channel["channel_id"], channel["channel_name"])

        if not schedule:
            print(f"Skipping ClickTheCity channel '{channel['channel_name']}', no schedule available.")
            continue

        for show in schedule:
            programme_elem = ET.SubElement(
                root, "programme", start=show["start"], stop=show["end"], channel=channel["channel_id"]
            )
            title_elem = ET.SubElement(programme_elem, "title")
            title_elem.text = show["title"]

    # Pretty print XML
    xml_str = minidom.parseString(ET.tostring(root, encoding='utf-8')).toprettyxml(indent="  ")
    epg_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'output', 'individual', 'clickthecity.xml')
    print(f"Writing ClickTheCity EPG data to {epg_path}...")

    try:
        os.makedirs(os.path.dirname(epg_path), exist_ok=True)
        with open(epg_path, "w", encoding="utf-8") as xml_file:
            xml_file.write(xml_str)
        print(f"ClickTheCity EPG data successfully written to {epg_path}")
    except Exception as e:
        print(f"Error writing ClickTheCity EPG data to {epg_path}: {e}")

    print("ClickTheCity EPG generation completed!")

if __name__ == "__main__":
    generate_epg()
