import pandas as pd, numpy as np
from datetime import datetime
import sys, json
import decimal


def test(t):
	print ("%s"%t)

def runKriging(X, tables):# = "ad_av_pos_accelerations"):
	import pymysql
	
	conn = pymysql.connect(host='localhost', database='testgr_dri', user='root', password='a5653091be1076')
	cur = conn.cursor()
		
	sh = {'ysc': 1, 'ssc': 2, 'theta': 1, 'beta': 1, 'gamma': 1, 's': 15}
	
	#X = pd.DataFrame(x) # The x input must be multiple segments ONLY: TO TEST
	res = []
	for table in tables:
		ysc = run_query(table, 'ysc', sh['ysc'], cur)
		ssc = run_query(table, 'ssc', sh['ssc'], cur)
		theta = run_query(table, 'theta', sh['theta'], cur)
		beta = run_query(table, 'beta', sh['beta'], cur)
		gamma = run_query(table, 'gamma', sh['gamma'], cur)
		s = run_query(table, 's', sh['s'], cur)

		r = KrigingCo2mpas(X, ysc, ssc, theta, beta, gamma, s)
		res.append(r)
		
	cur.close()
	conn.close()
	
	return res
	
	
	
def run_query(table, p, i, cur):
	q = "SELECT * FROM %s_%s"%(table, p)
	cur.execute(q)
	
	d = {}
	for row in cur:
		d[row[0]] = row[1:]
		
	return pd.DataFrame.from_dict(d, orient='index').reset_index(drop=True).astype('float')

	
	
def KrigingCo2mpas(x, ysc, ssc, theta, beta, gamma, s):
	"""
	x is an array/dataframe with the following parameters:
	'Capacity', 'Mass', 'Driving', 'Transmission', 'Traction', 'SS', 'BERS',
	'MechLoad', 'AR', 'RR', 'Slope', 'T', 'P_C', 'AvgV', 'InitT'
	
	# Check inKrig_MGT.csv file
	"""
	x = (x - ssc.ix[:,0].values) / ssc.ix[:,1].values
	
	sLL = x*beta.ix[1:, 0].values
	scalLossL = sLL.sum(axis=1) + beta.ix[0, 0]
	
	xtiled = np.tile(x, len(s)).reshape((-1, len(x.columns)))
	stiled = np.tile(s, (len(x), 1))
	
	corrFunFict = (stiled - xtiled)**2
	corrFunFict *= - theta.ix[:,0].values
	corrFun = np.exp(corrFunFict.sum(axis=1))
	corrFun = corrFun.reshape(len(x), len(s))
	
	sLR = corrFun*gamma.ix[:,0].values
	scalLossR = sLR.sum(axis=1)
	
	scalLoss= scalLossR + scalLossL
	
	return ysc.ix[0,0] + ysc.ix[1,0]*scalLoss



if __name__ == "__main__":
	
	# Load the data that PHP sent us
	try:
		x = json.loads(sys.argv[1])
		
	except:
		print("ERROR")
		sys.exit(1)

	lengthX=len(x)
	print(x)
#	Read all information from the array received
# 	First 12 values are the kriging inputs
# 	13th value represent the tables to be kriged(!):  0 ICE / 1 BEV / 2 HYB / 3 PHEV
#	14th values represent the car model to be taken:  0 DT_AT / 1 DT_MT / 2 GNA_AT / 3 GNA_MT / 4 GT_AT / 5 GT_MT 
#	following values enclosed in array are Speed, Slope and InitT	

	tableKrig=x[0][12] # the value for the table to be kriged
	modelKrig=x[0][13] # the value for the model to be kriged
	print(tableKrig)
	print(modelKrig)
	
	t = {0: ["_co2_emission"], # ICE
		 1: ["_av_neg_motive_powers","_av_pos_motive_powers","_time_percentage_neg_mov_pow","_time_percentage_pos_mov_pow"],
		 2: ["_fuel_consumption","_av_pos_engine_powers_out","_av_neg_motive_powers","_av_pos_motive_powers","_co2_emission","_willans_b","_willans_a","_time_percentage_neg_mov_pow","_time_percentage_pos_mov_pow"],
		 3: ["_av_vel_pos_mov_pow","_av_pos_accelerations","_fuel_consumption","_av_pos_engine_powers_out","_av_neg_motive_powers","_av_pos_motive_powers","_co2_emission","_willans_b","_willans_a","_time_percentage_neg_mov_pow","_time_percentage_pos_mov_pow"]
		 }
	
	l = len(x[1])
	avg_speed, slope, InitT = [], [], []
	for i in np.arange(l):
		avg_speed.append(x[1][i])
		slope.append(x[2][i])
		InitT.append(x[3][i])
	
	kriging_mega_array = [np.full(l, x[0][0]), 
						  np.full(l, x[0][1]),
						  np.full(l, x[0][2]),
						  np.full(l, x[0][3]),
						  np.full(l, x[0][4]),
						  np.full(l, x[0][5]),
						  np.full(l, x[0][6]),
						  np.full(l, x[0][7]),
						  np.array(avg_speed),
						  np.array(slope),
						  np.array(InitT),
						  np.full(l, x[0][11])]
	kriging_mega_array = pd.DataFrame(kriging_mega_array).T	

	#print(kriging_inputs)
	res = runKriging(kriging_mega_array, t[tableKrig])
# 	R = res
#	print(" ".join(str(r) for r in R))
	#Output for php
	#print(res)
	
# 	y1 = np.array([1500,1000,1,1,1,1,1,0,0,1.15,0.56454654612248684654,23,0.1,40,23])
# 	y2 = np.array([2500,2000,0,1,1,1,1,0,0,1.15,0.56454654612248684654,23,0.1,40,23])
# 	x = np.array([y1, y2]) 
# 	t = "ad_av_pos_accelerations"
#	runKriging(x, t)
