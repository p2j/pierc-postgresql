import psycopg2
import config
import datetime

class Pierc_DB:

	def __init__(self, server, portnum, db, username, pw):
		self.conn = psycopg2.connect ( host = server,
						port = portnum,
						user = username,
						password = pw,
						database = db )
		self.cursor = self.conn.cursor()


	def __del__(self):
		try:
			self.conn.close()
		except:
			return

	def create_table(self):
		self.cursor.execute(
		"""
			CREATE TABLE IF NOT EXISTS main
			(
				id      INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
				channel VARCHAR(16),
				name    VARCHAR(16),
				time    DATETIME,
				message TEXT,
				type    VARCHAR(10),
				hidden  CHAR(1)
			);

			""")

	def insert_line(self, channel, name, time, message, msgtype, hidden = "F"):

		"""
		Sample line: "sfucsss, danly, 12:33-09/11/2009, I love hats, normal, 0"
		"""
		query =	"INSERT INTO main (channel, name, time, message, type, hidden) VALUES" + \
		"(\""+self.conn.escape_string(channel)+ "\"," + \
		"\""+self.conn.escape_string(name)+"\"," + \
		"\""+time+"\"," + \
		"\""+self.conn.escape_string(message)+"\"," + \
		"\""+self.conn.escape_string(msgtype)+"\"," + \
		"\""+self.conn.escape_string(hidden)+"\")"

		self.cursor.execute(query)

	def commit(self):
		self.conn.commit()

if __name__ == "__main__":
	postgresql_config = config.config("postgresql_config.txt")
	db = Pierc_DB( postgresql_config["server"],
						int(postgresql_config["port"]),
						postgresql_config["database"],
						postgresql_config["user"],
						postgresql_config["password"])
	db.create_table()
