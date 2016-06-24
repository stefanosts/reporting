import pandas as pd, numpy as np
from datetime import datetime
import sys, json
import decimal


def test(t):
	print ("%s"%t)

def runKriging(x, tables):# = "ad_av_pos_accelerations"):
	import pymysql
	
	conn = pymysql.connect(host='localhost', database='testgr_dri', user='root', password='a5653091be1076')
	cur = conn.cursor()
	
	sh = {'ysc': 1, 'ssc': 2, 'theta': 1, 'beta': 1, 'gamma': 1, 's': 15}
	
	X = pd.DataFrame(x) # The x input must be multiple segments ONLY: TO TEST
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
	
	td = {0: ["ad_av_pos_accelerations", "ad_av_vel_pos_mov_pow", "ad_fuel_consumption", "ad_av_pos_engine_powers_out"]}
	t = td[x[0][15]]
	
	l = []
	#print(lengthX)
	
	for i in np.arange(int(lengthX)):	
		a=x[i]
		li = [a[0], a[1], a[2], a[3], a[4], a[5], a[6], a[7],
			a[8], a[9], a[10], a[11], a[12], a[13], a[14]]
		l.append(li)
	X = np.array(l)
	
	res = runKriging(X, t)
# 	R = res
# 	print(" ".join(str(r) for r in R))

	
# 	y1 = np.array([1500,1000,1,1,1,1,1,0,0,1.15,0.56454654612248684654,23,0.1,40,23])
# 	y2 = np.array([2500,2000,0,1,1,1,1,0,0,1.15,0.56454654612248684654,23,0.1,40,23])
# 	x = np.array([y1, y2]) 
# 	t = "ad_av_pos_accelerations"
#	runKriging(x, t)
