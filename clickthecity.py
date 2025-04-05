import requests
from bs4 import BeautifulSoup
import os
from datetime import datetime, timedelta
import pytz

def fetch_clickthecity_schedule():
    url = "https://www.clickthecity.com/tv-schedule/"
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    }
    try:
        response = requests.get(url, headers=headers)
        response.raise_for_status()  # Raise an exception for bad status codes
        soup = BeautifulSoup(response.content, 'html.parser')
        return soup
    except requests.exceptions.RequestException as e:
        print(f"Error fetching ClickTheCity schedule: {e}")
        return None

def parse_schedule(soup):
    channels = {}
    schedule_container = soup.find('div', class_='tv-schedule-container')
    if schedule_container:
        channel_sections = schedule_container.find_all('div', class_='channel-schedule')
        for channel_section in channel_sections:
            channel_name_tag = channel_section.find('h3', class_='channel-name')
            if channel_name_tag:
                channel_name = channel_name_tag.text.strip()
                channels[channel_name] = []
                program_items = channel_section.find_all('li', class_='program-item')
                for program_item in program_items:
                    time_tag = program_item.find('span', class_='program-time')
                    title_tag = program_item.find('span', class_='program-title')
                    if time_tag and title_tag:
                        time_str = time_tag.text.strip()
                        title = title_tag.text.strip()
                        channels[channel_name].append({'time': time_str, 'title': title})
    return channels

def format_schedule_to_xmltv(schedule):
    xml = '<?xml version="1.0" encoding="UTF-8"?>\n'
    xml += '<tv generator-info-name="ClickTheCity EPG" generator-info-url="https://www.clickthecity.com">\n'

    channel_ids = {}
    counter = 1
    for channel_name in sorted(schedule.keys()):
        channel_id = f"CTC-{counter}"
        channel_ids[channel_name] = channel_id
        xml += f'  <channel id="{channel_id}">\n'
        xml += f'    <display-name lang="en">{channel_name}</display-name>\n'
        xml += '  </channel>\n'
        counter += 1

    philippine_timezone = pytz.timezone('Asia/Manila')
    now = datetime.now(philippine_timezone).date()

    for channel_name, programs in schedule.items():
        channel_id = channel_ids[channel_name]
        for program in programs:
            time_str = program['time']
            title = program['title']

            try:
                start_time_dt = datetime.strptime(f"{now.year}-{now.month}-{now.day} {time_str}", "%Y-%m-%d %I:%M %p")
                # Assuming programs are generally for the current and next day to handle overnight schedules
                if start_time_dt.hour < 6: # Programs before 6 AM are likely for the current day (after midnight)
                    program_date = now
                else:
                    program_date = now

                start_aware = philippine_timezone.localize(start_time_dt)
                stop_aware = start_aware + timedelta(hours=1) # Default stop time of 1 hour, adjust as needed

                xml += f'  <programme start="{start_aware.strftime("%Y%m%d%H%M%S %z")}" stop="{stop_aware.strftime("%Y%m%d%H%M%S %z")}" channel="{channel_id}">\n'
                xml += f'    <title lang="en">{title}</title>\n'
                xml += '  </programme>\n'

            except ValueError as e:
                print(f"Error parsing time '{time_str}' for '{title}' on '{channel_name}': {e}")

    xml += '</tv>\n'
    return xml

if __name__ == "__main__":
    print("ClickTheCity EPG script started.")
    soup = fetch_clickthecity_schedule()
    if soup:
        schedule = parse_schedule(soup)
        if schedule:
            xml_output = format_schedule_to_xmltv(schedule)
            output_dir = "output"
            output_filename = os.path.join(output_dir, "clickthecity.xml")
            os.makedirs(output_dir, exist_ok=True)
            with open(output_filename, "w", encoding="utf-8") as f:
                f.write(xml_output)
            print(f"Successfully wrote to: {output_filename}")
        else:
            print("No schedule data parsed.")
    else:
        print("Failed to fetch schedule.")
    print("ClickTheCity EPG script finished.")
