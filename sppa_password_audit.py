#!/usr/bin/python3
# -*- coding: utf-8 -*-
import binascii
import gzip
import re
import xml.etree.ElementTree as ET
import hashlib
import struct
import sys, os, argparse


class pDataModifier(object):

	def __init__(self, filename):
		self.filename = filename
		self.bJavaSerializationData = self.extractEXM(filename)
		self.oXML = ET.fromstring(self.bJavaSerializationData[7:])
		self.toUpdate = {}
	
	def extractEXM(self, filename):
		buf = open(filename, "r").read().strip()
		buf = binascii.a2b_base64(buf)
		return gzip.decompress(buf)

	def setXMLFieldbyUserID(self, userid, field, value):
		for item in self.oXML: 
			if item.attrib["userid"] == userid: 
				if (item.find(field) == None): item.attrib[field]=value
				else: item.find(field).text = value


	def getXMLFieldbyUserID(self, userid, field):
		for item in self.oXML: 
			if item.attrib["userid"] == userid: 
				if (item.find(field) == None): return item.attrib[field]
				else: return item.find(field).text

	def setXMLFieldbyLoginName(self, loginName, field, value):
		return self.setXMLFieldbyUserID(self.getUserIDbyLoginName(loginName), field, value)

	def getXMLFieldbyLoginName(self, loginName, field):
		return self.getXMLFieldbyUserID(self.getUserIDbyLoginName(loginName), field)

	def getUserIDbyLoginName(self, loginName):
		for item in self.oXML: 
			if item.find("loginname").text == loginName: return item.attrib["userid"]

	def calcSHA256(self, loginName, password):
		userID = self.getUserIDbyLoginName(loginName)
		uniqueSalt = self.getXMLFieldbyUserID(userID, "s")
		numberOfIterations = max([min([int(self.getXMLFieldbyUserID(userID, "i")), 200000]), 100000]) + 78742;
		commonSalt = "e8cJP2Wv89"#for all users
		ret_val = hashlib.sha256((uniqueSalt + loginName + password + commonSalt).encode("cp1252")).digest()
		for i in range(numberOfIterations):
			ret_val = hashlib.sha256(ret_val).digest()
		return ret_val

	def setNewPasswordbyLoginName(self, loginName, newPass):
		bSHA256 = self.calcSHA256(loginName, newPass)
		print("NEW SHA256:", str(binascii.b2a_hex(bSHA256), "utf8"))
		self.toUpdate[self.getXMLFieldbyLoginName(loginName, "password")] =  str(binascii.b2a_hex(bSHA256), "utf8")
		self.setXMLFieldbyLoginName(loginName, "password", str(binascii.b2a_hex(bSHA256),"utf8"))


	def updateXMLField(self):
		for k,v in self.toUpdate.items():
			index = self.bJavaSerializationData.index(k.encode("utf8"))
			self.bJavaSerializationData = self.bJavaSerializationData[:index] + v.encode("utf8") + self.bJavaSerializationData[index+len(v):]

	def createEXM(self, filename):
		buf = binascii.b2a_base64(gzip.compress(self.bJavaSerializationData)).strip()		
		open(filename, "wb").write(buf)

	def updatePassword(self, loginName, newPass):
		self.setNewPasswordbyLoginName(loginName, newPass)
		self.updateXMLField()
		self.createEXM(self.filename + "_modify")

	def updateHashParam(self, loginName, salt="TsMyEncKey", i=133700):
		self.toUpdate[self.getXMLFieldbyLoginName(loginName, "s")] =  salt
		self.toUpdate[self.getXMLFieldbyLoginName(loginName, "i")] =  str(i)
		self.setXMLFieldbyLoginName(loginName, "s", salt)
		self.setXMLFieldbyLoginName(loginName, "i", str(i))
		self.updateXMLField()
		self.createEXM(self.filename + "_modify")

	def getCalcPasswordParams(self, loginName):
		if self.getUserIDbyLoginName(loginName) == None: 
			return "Username %s not found in %s" % (loginName, self.filename)
		else:
			return {k:self.getXMLFieldbyLoginName(loginName, k) for k in ["password", "s", "i"]}


parser = argparse.ArgumentParser(description="STTP password changer")
action = parser.add_mutually_exclusive_group(required=True)
action.add_argument('--extract', action='store_true', help='Extract password parameters for specific users')
action.add_argument('--update', action='store_true', help='Update password for specific users')
parser.add_argument("-f", "--pdata", help="pdata1.exm file (default pdata1.exm)")
parser.add_argument("-u", "--username", help="user for whow you want to change a password")
parser.add_argument("-p", "--new-password", help="new password for the specific user")
ARGS = parser.parse_args() 


if __name__=="__main__":
	if ARGS.pdata == None: ARGS.pdata = "pdata1.exm" 
	if os.path.isfile(ARGS.pdata) == False: 
		print("%s is not exist (use -f PDATA)" % ARGS.pdata)
		exit()
	if ARGS.username == None: 
		print("you need set username (use -u USERNAME)")
		exit()
	oModifier = pDataModifier(ARGS.pdata)
	if ARGS.update == True:
		if ARGS.new_password == None: 
			print("you need set new password (use -p NEW_PASSWORD)")
			exit()
		oModifier.updatePassword(ARGS.username, ARGS.new_password)
		exit()
	if ARGS.extract == True:
		print(oModifier.getCalcPasswordParams(ARGS.username))
		exit()

